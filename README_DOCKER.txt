COMO RODAR COM DOCKER

1) Entre na pasta do projeto:
   cd Poker_simulator_docker

2) Suba os containers:
   docker compose up --build -d

3) Acesse o jogo:
   http://localhost:8080

4) Acesse o phpMyAdmin, se precisar:
   http://localhost:8081

Dados do banco:
- Host interno: db
- Banco: poker_game
- Usuario: poker_user
- Senha: poker_pass
- Porta externa MySQL: 3307

Comandos úteis:
- Ver logs: docker compose logs -f app
- Parar: docker compose down
- Parar e apagar banco: docker compose down -v
- Rebuild: docker compose up --build -d
