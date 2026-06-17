POKER SIMULATOR - Docker e XAMPP

1) Rodar com Docker
-------------------
Entre na pasta do projeto e execute:

docker compose up --build -d

Acesse:
http://localhost:8080

Parar:
docker compose down

Apagar banco também:
docker compose down -v


2) Rodar com XAMPP / Apache
---------------------------
Copie a pasta Poker_simulator para dentro do htdocs.

Ligue Apache e MySQL no XAMPP.

Acesse:
http://localhost/Poker_simulator

Se o MySQL estiver usando porta 3307, abra config/database.php e altere:
$port = getenv('DB_PORT') ?: '3307';

Usuário padrão do XAMPP:
root
Senha padrão:
vazio
