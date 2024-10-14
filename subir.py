import pandas as pd
from datetime import datetime
import mysql.connector

# Função para inserir os dados no banco de dados
def insert_data_to_db(data):
    try:
        # Conexão com o banco de dados MySQL
        conn = mysql.connector.connect(
            host="localhost",
            user="root",
            password="",  # Adicione sua senha, se necessário
            database="roteirizador"
        )
        
        cursor = conn.cursor()

        # Script de inserção no banco
        insert_query = """
        INSERT INTO rotas (latitude, longitude, bairro, data_rota, motorista_id, roteiro)
        VALUES (%s, %s, %s, %s, %s, %s)
        """

        # Loop pelos dados e inserção no banco
        for index, row in data.iterrows():
            cursor.execute(insert_query, (
                row['Latitude'], row['Longitude'], row['Bairro'],
                datetime.now().strftime('%Y-%m-%d'),  # data da rota (hoje)
                2,  # motorista_id fixo como 2
                108  # roteiro fixo como 108
            ))

        # Commit e fechamento da conexão
        conn.commit()
        cursor.close()
        conn.close()
        print("Dados inseridos com sucesso!")

    except Exception as e:
        print(f"Ocorreu um erro: {e}")

# Leitura do arquivo XLSX
file_path = 'C:\\Users\\luhan\\Downloads\\FOR106 - TESTE2.xlsx'  # Altere para o caminho correto do seu arquivo

try:
    # Leitura do arquivo XLSX
    xls_data = pd.read_excel(file_path)

    # Filtrando as colunas necessárias
    filtered_data = xls_data[['Latitude', 'Longitude', 'Bairro']]

    # Inserindo os dados no banco
    insert_data_to_db(filtered_data)

except Exception as e:
    print(f"Ocorreu um erro ao ler o arquivo ou inserir os dados: {e}")
