POKER SIMULATOR - VERSÃO CORRIGIDA

Como rodar no XAMPP:
1. Copie a pasta Poker_simulator para htdocs.
2. Ligue Apache e MySQL.
3. Abra no navegador: http://localhost/Poker_simulator
4. Crie uma conta e jogue.

Importante:
- O banco poker_game e as tabelas são criados automaticamente.
- Se seu MySQL estiver na porta 3307, altere em config/database.php: $port = '3307';
- Se aparecer erro de conexão, o problema está no MySQL/porta/senha do XAMPP.

O jogo é Texas Hold'em simplificado contra bots, com login, fichas persistidas, mesa, animações, rodadas e histórico.
