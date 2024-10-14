<?php
session_start();
if (!isset($_SESSION['motorista_id'])) {
    header("Location: login.php");
    exit;
}

$motorista_id = $_SESSION['motorista_id'];

// Conexão ao banco de dados
$conn = new mysqli("localhost", "root", "", "roteirizador");

// Verificar se a conexão foi bem-sucedida
if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

// Consulta para buscar as rotas do motorista logado no dia atual
$sql = "SELECT latitude, longitude, bairro FROM rotas WHERE motorista_id = ? AND data_rota = CURDATE()";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $motorista_id);
$stmt->execute();
$result = $stmt->get_result();

// Armazena as coordenadas e informações em um array
$coords = [];
$locations = [];
while ($row = $result->fetch_assoc()) {
    if (!empty($row['latitude']) && !empty($row['longitude'])) {
        $coords[] = [$row['longitude'], $row['latitude']];
        $locations[] = $row['bairro']; // Usando bairro como identificador
    }
}

// Função para dividir em múltiplos blocos de até 25 pontos
function divide_into_chunks($coords, $chunk_size = 25) {
    return array_chunk($coords, $chunk_size);
}

// Divide os pontos em blocos de até 25
$chunks = divide_into_chunks($coords);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rota do Motorista (Otimizada)</title>
    <link href='https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css' rel='stylesheet' />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.14.0/sortable.min.css">
    <style>
        body { margin: 0; padding: 0; }
        #map { position: absolute; top: 0; bottom: 0; width: 100%; height: 500px; }
        #locations { margin-top: 520px; }
        .location-item { padding: 10px; background-color: #f0f0f0; margin-bottom: 5px; cursor: move; }
    </style>
</head>
<body>
    <div id="map"></div>
    <div id="locations">
        <h3>Ordem da Rota (Arraste para Reorganizar):</h3>
        <ul id="sortable">
            <?php foreach ($locations as $index => $location): ?>
                <li class="location-item" data-index="<?= $index; ?>"><?= ($index + 1) . '. ' . $location; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>

    <script src='https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js'></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.14.0/Sortable.min.js"></script>
    <script>
        mapboxgl.accessToken = 'pk.eyJ1IjoibHVoYW56aWsiLCJhIjoiY20yM2tnYm5qMDZ1ZTJpb2gyN2t4dGo1YiJ9.5xz3Zj4HtGm2hCpi8xCNIA';

        var map = new mapboxgl.Map({
            container: 'map',
            style: 'mapbox://styles/mapbox/streets-v11',
            center: [-38.566946, -3.753604], // Centraliza o mapa nas coordenadas de Fortaleza
            zoom: 12
        });

        var coordinates = <?php echo json_encode($coords); ?>;
        var chunks = <?php echo json_encode($chunks); ?>;

        // Função para adicionar marcadores numerados no mapa
        function addMarkers(waypoints) {
            // Remove os marcadores anteriores
            document.querySelectorAll('.mapboxgl-marker').forEach(marker => marker.remove());

            // Adiciona novos marcadores numerados
            waypoints.forEach((coord, index) => {
                var el = document.createElement('div');
                el.className = 'marker';
                el.style.backgroundColor = '#3FB1CE';
                el.style.width = '30px';
                el.style.height = '30px';
                el.style.borderRadius = '50%';
                el.style.textAlign = 'center';
                el.style.color = 'white';
                el.innerText = (index + 1).toString();

                new mapboxgl.Marker(el).setLngLat(coord).addTo(map);
            });
        }

        // Função para carregar a rota dividida em múltiplas requisições
        function loadMultipleRoutes(chunks) {
            if (map.getSource('route')) {
                map.removeLayer('route');
                map.removeSource('route');
            }

            var allRoutes = [];

            chunks.forEach(function(chunk, index) {
                var coordsStr = chunk.map(coord => coord.join(',')).join(';');
                
                fetch(`https://api.mapbox.com/directions/v5/mapbox/driving/${coordsStr}?geometries=geojson&access_token=${mapboxgl.accessToken}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.routes && data.routes.length > 0) {
                            var route = data.routes[0].geometry;
                            allRoutes.push(route);

                            if (allRoutes.length === chunks.length) {
                                var combinedRoute = {
                                    type: 'Feature',
                                    properties: {},
                                    geometry: {
                                        type: 'LineString',
                                        coordinates: allRoutes.flatMap(route => route.coordinates)
                                    }
                                };

                                map.addSource('route', {
                                    type: 'geojson',
                                    data: combinedRoute
                                });

                                map.addLayer({
                                    id: 'route',
                                    type: 'line',
                                    source: 'route',
                                    layout: {
                                        'line-join': 'round',
                                        'line-cap': 'round'
                                    },
                                    paint: {
                                        'line-color': '#FF0000',
                                        'line-width': 5
                                    }
                                });

                                // Adiciona os marcadores numerados após a rota
                                addMarkers(coordinates);
                            }
                        }
                    });
            });
        }

        loadMultipleRoutes(chunks);

        // Função para capturar a nova ordem e recalcular a rota e os marcadores
        function updateOrderAndReloadRoute() {
            var newOrder = [];
            document.querySelectorAll('#sortable .location-item').forEach(function (item, index) {
                var itemIndex = item.getAttribute('data-index');
                newOrder.push(coordinates[itemIndex]);

                // Atualiza a numeração na lista
                item.innerHTML = (index + 1) + '. ' + item.innerText.split('. ')[1];
            });

            var newChunks = divideIntoChunks(newOrder, 25);
            loadMultipleRoutes(newChunks); // Recarrega as rotas com a nova ordem
            addMarkers(newOrder); // Atualiza os marcadores com a nova ordem
        }

        var sortable = new Sortable(document.getElementById('sortable'), {
            animation: 150,
            onEnd: function () {
                updateOrderAndReloadRoute();
            }
        });

        function divideIntoChunks(coords, chunkSize) {
            var chunks = [];
            for (var i = 0; i < coords.length; i += chunkSize) {
                chunks.push(coords.slice(i, i + chunkSize));
            }
            return chunks;
        }
    </script>
</body>
</html>
