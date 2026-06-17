POKER SIMULATOR - Docker e XAMPP

1) Rodar com Docker
-------------------
Entre na pasta do projeto e execute:

docker compose up --build -d

Acesse:
http://localhost:8080

phpMyAdmin:
http://localhost:8081

Parar:
docker compose down

Apagar banco também:
docker compose down -v


2) Rodar com XAMPP / Apache
---------------------------
Copie a pasta Poker_simulator_responsivo_blinds para dentro do htdocs.

Ligue Apache e MySQL no XAMPP.

Acesse:
http://localhost/Poker_simulator_responsivo_blinds

Se o MySQL estiver usando porta 3307, abra config/database.php e altere:
$port = getenv('DB_PORT') ?: '3307';

Usuário padrão do XAMPP:
root
Senha padrão:
vazio


3) Alterações desta versão
--------------------------
- Mesa responsiva por escala: mantém o mesmo layout e diminui tudo proporcionalmente em monitores menores.
- Small blind e big blind passam para o próximo jogador vivo a cada mão.
- O dealer/botão também gira a cada mão.
- Pré-flop e pós-flop seguem a ordem básica do Texas Hold'em, incluindo regra de heads-up.
- Continua funcionando tanto em Docker quanto em Apache/XAMPP.
