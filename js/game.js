let game = null;
let busy = false;
let botTimer = null;
let nextHandTimer = null;
let lastFinishedKey = "";
let holdEndPanel = false;

const $ = (s) => document.querySelector(s);
const $$ = (s) => [...document.querySelectorAll(s)];
const names = ["VOCÊ", "L", "M", "N"];
const DELAY = {
  humanAction: 1150,
  botThinkMin: 1350,
  botThinkMax: 2100,
  betweenBots: 850,
  reveal: 1650,
  endHand: 4200,
  newHand: 1200,
  message: 1900,
};

window.addEventListener("DOMContentLoaded", async () => {
  bindModeButtons();
  bindControls();
  setupResponsiveTable();

  await loadState();

  if (game) {
    showTable();
    if (game.pendingBlinds) await runHandIntroAndBlinds(true);
    botLoop();
  } else {
    showModeSelect();
  }
});

function bindModeButtons() {
  $$(".mode-btn").forEach((btn) => {
    btn.addEventListener("click", async () => {
      const mode = btn.dataset.mode || "casual";
      await startHand(true, mode);
    });
  });
}

function showModeSelect() {
  $("#modeSelect")?.classList.remove("hidden");
  $("#tableShell")?.classList.add("hidden");
  $("#controls")?.classList.add("hidden");
}

function showTable() {
  $("#modeSelect")?.classList.add("hidden");
  $("#tableShell")?.classList.remove("hidden");
}

function bindControls() {
  $$("#controls button[data-action]").forEach((btn) => {
    btn.type = "button";
    btn.addEventListener("click", async (ev) => {
      ev.preventDefault();
      if (busy || !game || game.turn !== 0 || game.finished) return;

      busy = true;
      clearTimeout(botTimer);
      clearTimeout(nextHandTimer);
      renderControls();

      try {
        const action = btn.dataset.action;
        const raise = Number($("#raiseValue")?.value || 20);
        if (action === "raise") {
          const human = game.players[0];
          const toCall = Math.max(0, (game.currentBet || 0) - (human.bet || 0));
          const maxRaise = Math.max(0, (human.chips || 0) - toCall);
          if (raise > maxRaise) {
            showMessage("Você não tem fichas suficientes para essa aposta.");
            return;
          }
        }
        await post("api/action.php", { action, raise });
        await animateLastAction();
        render();
        await wait(DELAY.humanAction);
      } catch (e) {
        showMessage(e.message || "Erro ao jogar.");
      } finally {
        busy = false;
        render();
        continueGameFlow();
      }
    });
  });

  $("#newHand")?.addEventListener("click", (ev) => {
    ev.preventDefault();
    startHand(true);
  });
}

async function loadState() {
  try {
    const data = await requestJson("api/state.php");
    game = data.game;
    render();
  } catch (e) {
    showMessage(e.message || "Não foi possível carregar o jogo.");
  }
}

async function startHand(showIntro = true, mode = null) {
  busy = true;
  clearTimeout(botTimer);
  clearTimeout(nextHandTimer);

  try {
    const form = new FormData();

    if (mode) {
      form.append("mode", mode);
    }

    const data = await requestJson("api/start.php", {
      method: "POST",
      body: form,
    });

    game = data.game;
    lastFinishedKey = "";
    $("#endPanel")?.classList.add("hidden");

    clearCardCaches();
    showTable();
    render();

    if (showIntro) await runHandIntroAndBlinds(true);
    else if (game.pendingBlinds) await applyPendingBlinds();
  } catch (e) {
    showMessage(e.message || "Erro ao iniciar a mão.");
  } finally {
    busy = false;
    render();
    continueGameFlow();
  }
}
async function nextHand() {
  busy = true;
  clearTimeout(botTimer);
  clearTimeout(nextHandTimer);
  try {
    const data = await requestJson("api/next_hand.php", { method: "POST" });
    game = data.game;
    lastFinishedKey = "";
    $("#endPanel")?.classList.add("hidden");
    clearCardCaches();
    render();
    if (!game.gameOver) await runHandIntroAndBlinds(false);
  } catch (e) {
    showMessage(e.message || "Erro ao iniciar próxima mão.");
  } finally {
    busy = false;
    render();
    continueGameFlow();
  }
}

async function runHandIntroAndBlinds(firstEntry) {
  await showIntroSequence(
    firstEntry ? "Partida iniciando" : "Nova mão",
    firstEntry,
  );
  if (game && game.pendingBlinds) {
    await applyPendingBlinds();
    showMessage("Blinds colocados.");
    render();
    await wait(450);
  }
}

async function applyPendingBlinds() {
  const data = await requestJson("api/apply_blinds.php", { method: "POST" });
  game = data.game;
}

async function post(url, body) {
  const form = new FormData();
  Object.entries(body).forEach(([k, v]) => form.append(k, v));
  const data = await requestJson(url, { method: "POST", body: form });
  game = data.game;
}

async function requestJson(url, options = {}) {
  const res = await fetch(url, { ...options, cache: "no-store" });
  const text = await res.text();
  let data;
  try {
    data = JSON.parse(text);
  } catch {
    throw new Error("Resposta inválida do servidor. Verifique PHP/MySQL.");
  }
  if (!res.ok || data.ok === false)
    throw new Error(data.error || "Erro no servidor.");
  return data;
}

function continueGameFlow() {
  if (!game) return;
  if (game.pendingBlinds) return;
  if (game.finished) {
    scheduleNextHand();
    return;
  }
  if (game.turn !== 0) botTimer = setTimeout(botLoop, DELAY.betweenBots);
}

async function botLoop() {
  clearTimeout(botTimer);
  if (!game || game.finished || game.turn === 0 || busy) {
    render();
    return;
  }

  busy = true;
  renderControls();
  const p = game.players[game.turn];
  showMessage(`${p.name} pensando...`);
  await wait(
    DELAY.botThinkMin + Math.random() * (DELAY.botThinkMax - DELAY.botThinkMin),
  );

  try {
    await post("api/bot_step.php", {});
    await animateLastAction();
  } catch (e) {
    showMessage(e.message || "Erro na jogada do bot.");
  } finally {
    busy = false;
    render();
    continueGameFlow();
  }
}

function scheduleNextHand() {
  const key = `${game.handResult}|${game.pot}|${game.players.map((p) => p.chips).join("-")}|${game.gameOver ? "over" : "play"}`;
  if (key === lastFinishedKey) return;
  lastFinishedKey = key;
  renderEnd();
  clearTimeout(nextHandTimer);
  if (!game.gameOver)
    nextHandTimer = setTimeout(() => nextHand(), DELAY.endHand);
}

function setupResponsiveTable() {
  scaleTable();
  window.addEventListener("resize", scaleTable, { passive: true });
  window.addEventListener(
    "orientationchange",
    () => setTimeout(scaleTable, 150),
    { passive: true },
  );
}

function scaleTable() {
  const shell = document.querySelector(".table-shell");
  const table = document.querySelector(".poker-table");
  if (!shell || !table) return;
  const baseW = 1540;
  const baseH = 840;
  const rect = shell.getBoundingClientRect();
  const scale = Math.min(rect.width / baseW, rect.height / baseH, 1);
  document.documentElement.style.setProperty(
    "--table-scale",
    String(Math.max(0.45, scale)),
  );
}

function render() {
  scaleTable();
  if (!game) return;
  $("#pot b").textContent = game.pot ?? 0;
  $("#stage").textContent = stageLabel(game.stage || "preflop");
  renderBoard();
  game.players.forEach((p, i) => renderPlayer(p, i));
  renderControls();
  renderLog();
  if (game.finished && !holdEndPanel) renderEnd();
  else $("#endPanel")?.classList.add("hidden");
}

function renderControls() {
  const controls = $("#controls");
  if (!controls || !game) return;
  const human = game.players[0];
  const toCall = Math.max(0, (game.currentBet || 0) - (human.bet || 0));
  const isHumanTurn =
    !game.pendingBlinds &&
    !game.finished &&
    !game.gameOver &&
    game.turn === 0 &&
    !busy &&
    !human.folded &&
    !human.eliminated;
  controls.classList.toggle("hidden", !isHumanTurn);

  const checkBtn = controls.querySelector('[data-action="check"]');
  const callBtn = controls.querySelector('[data-action="call"]');
  if (checkBtn) {
    checkBtn.disabled = toCall > 0 || !isHumanTurn;
    checkBtn.textContent = "Check";
  }
  if (callBtn) {
    callBtn.disabled = toCall === 0 || !isHumanTurn;
    callBtn.textContent = toCall > 0 ? `Pagar ${toCall}` : "Pagar";
  }
  const raiseInput = $("#raiseValue");
  if (raiseInput) {
    const maxRaise = Math.max(0, (human.chips || 0) - toCall);
    raiseInput.max = String(maxRaise);
    raiseInput.title = `Máximo: ${maxRaise}`;
  }
  controls.querySelectorAll("button, input").forEach((el) => {
    if (!["check", "call"].includes(el.dataset?.action || ""))
      el.disabled = !isHumanTurn;
  });
}

function renderBoard() {
  const board = $("#board");
  const cards = [...(game.community || [])];
  while (cards.length < 5) cards.push(null);
  renderCards(board, cards, false, "board");
}

function renderPlayer(p, i) {
  const seat = $(`.seat-${i}`);
  if (!seat) return;
  seat.classList.toggle("active", game.turn === i && !game.finished);
  seat.classList.toggle("folded", !!p.folded);
  seat.classList.toggle("winner", !!(game.winners && game.winners.includes(i)));
  seat.classList.toggle("eliminated", !!p.eliminated);
  seat.querySelector(".avatar").textContent = i === 0 ? "VOCÊ" : names[i];
  let role = seat.querySelector(".role-badge");
  if (!role) {
    role = document.createElement("div");
    role.className = "role-badge";
    seat.appendChild(role);
  }
  role.textContent = p.role || "";
  role.classList.toggle("empty", !p.role);
  seat.querySelector(".info b").textContent = p.name;
  seat.querySelector(".info span").textContent = `${p.chips} fichas`;
  seat.querySelector(".info small").textContent = statusText(p, i);
  const hidden = i !== 0 && !game.finished;
  renderCards(seat.querySelector(".cards"), p.cards || [], hidden, `p${i}`);
  seat.querySelector(".bet").textContent = p.bet > 0 ? p.bet : "";
}

function renderCards(container, cards, hidden, cacheName) {
  if (!container) return;
  const signature = cards
    .map((c) => (c ? `${hidden ? "X" : c.value + c.suit}` : "empty"))
    .join("|");
  if (container.dataset.signature === signature) return;
  container.dataset.signature = signature;
  container.innerHTML = "";
  cards.forEach((c, idx) =>
    container.appendChild(c ? cardEl(c, hidden, idx) : emptyCard()),
  );
}

function clearCardCaches() {
  $$(".cards,.board").forEach((el) => {
    el.dataset.signature = "";
    el.innerHTML = "";
  });
}

function statusText(p, i) {
  if (p.eliminated) return "Fora do jogo";
  if (p.folded) return "Fold";
  if (p.allIn) return "All-in";
  if (game.finished) return p.result || "";
  if (game.turn === i) return i === 0 ? "Sua vez" : "Pensando";
  if (p.bet > 0) return `Aposta ${p.bet}`;
  return "Na mão";
}

function cardEl(c, hidden = false, idx = 0) {
  const el = document.createElement("div");
  el.className = "card " + (hidden ? "back" : color(c));
  el.style.animationDelay = `${idx * 0.12}s`;
  el.textContent = hidden ? "" : `${c.value}${c.suit}`;
  return el;
}
function emptyCard() {
  const el = document.createElement("div");
  el.className = "card empty";
  return el;
}
function color(c) {
  return c.suit === "♥" || c.suit === "♦" ? "red" : "black";
}
function stageLabel(s) {
  return (
    {
      preflop: "PRÉ-FLOP",
      flop: "FLOP",
      turn: "TURN",
      river: "RIVER",
      showdown: "SHOWDOWN",
    }[s] || s
  ).toUpperCase();
}

async function animateLastAction() {
  if (!game || !game.lastAction) return;
  const action = game.lastAction;
  holdEndPanel = !!game.finished;
  showMessage(action.text || "");
  render();
  if (["call", "raise", "allin"].includes(action.type))
    flyChipFromSeat(action.player);
  if (action.type === "allin") animateAllIn(action.player);
  if (action.type === "fold") animateFold(action.player);
  const pause = ["reveal", "showdown"].includes(action.type)
    ? DELAY.reveal
    : game.finished
      ? 2300
      : DELAY.humanAction;
  await wait(pause);
  holdEndPanel = false;
}

function animateAllIn(i) {
  if (i < 0) return;
  const seat = $(`.seat-${i}`);
  const table = $("#table");
  if (!seat) return;
  seat.classList.add("allin-burst");
  table?.classList.add("allin-table-flash");
  setTimeout(() => seat.classList.remove("allin-burst"), 1200);
  setTimeout(() => table?.classList.remove("allin-table-flash"), 1200);
  for (let n = 0; n < 8; n++) setTimeout(() => flyChipFromSeat(i), n * 90);
}

function animateFold(i) {
  if (i < 0) return;
  const seat = $(`.seat-${i}`);
  if (!seat) return;
  seat.classList.add("fold-away");
  setTimeout(() => seat.classList.remove("fold-away"), 1100);
}

async function showIntroSequence(title, firstEntry) {
  const intro = $("#tableIntro");
  if (!intro || !game) {
    showMessage(title || "Nova mão");
    await wait(DELAY.newHand);
    return;
  }
  const alive = game.players.filter((p) => !p.eliminated);
  intro.querySelector("h2").textContent = firstEntry
    ? "Entrando na mesa"
    : title;
  intro.querySelector(".intro-players").innerHTML = alive
    .map((p) => {
      const introChips = firstEntry ? 1000 : Number(p.chips || 0);
      return `<span>${escapeHtml(p.name)}<small>${introChips} fichas</small></span>`;
    })
    .join("");
  intro.querySelector("p").textContent = firstEntry
    ? "Preparando jogadores..."
    : "Preparando próxima mão...";
  intro.classList.remove("hidden");
  await wait(firstEntry ? 1250 : 900);
  intro.querySelector("p").textContent = "Distribuindo as cartas...";
  $("#table")?.classList.add("dealing");
  await wait(1200);
  intro.querySelector("p").textContent = "A rodada começou.";
  await wait(650);
  intro.classList.add("hidden");
  $("#table")?.classList.remove("dealing");
}

function flyChipFromSeat(i) {
  if (i < 0) return;
  const seat = $(`.seat-${i}`);
  const pot = $("#pot");
  if (!seat || !pot) return;
  const a = seat.getBoundingClientRect();
  const b = pot.getBoundingClientRect();
  const chip = document.createElement("div");
  chip.className = "fly-chip";
  chip.style.left = `${a.left + a.width / 2}px`;
  chip.style.top = `${a.top + a.height / 2}px`;
  document.body.appendChild(chip);
  requestAnimationFrame(() => {
    chip.style.left = `${b.left + b.width / 2}px`;
    chip.style.top = `${b.top + b.height / 2}px`;
    chip.style.transform = "scale(.55)";
  });
  setTimeout(() => chip.remove(), 1050);
}

function showMessage(text) {
  const box = $("#visualMessage");
  if (!box) return;
  box.textContent = text || "";
  box.classList.add("show");
  clearTimeout(box._t);
  box._t = setTimeout(() => box.classList.remove("show"), DELAY.message);
}

function renderEnd() {
  const panel = $("#endPanel");
  if (!panel) return;
  panel.classList.remove("hidden");
  panel.querySelector("h2").textContent = game.gameOver
    ? "Fim do jogo"
    : game.handResult || "Fim da mão";

  const rows = game.players
    .map((p, i) => {
      const hand = (p.cards || []).map((c) => miniCard(c)).join("");
      const result =
        p.result ||
        (game.winners && game.winners.includes(i)
          ? "Vencedor da mão"
          : p.folded
            ? "Fold"
            : "Na mão");
      const out = p.eliminated ? " — fora" : "";
      return `
      <div class="result-row">
        <div class="result-name">${escapeHtml(p.name)}</div>
        <div class="result-detail">
          <span class="mini-cards">${hand}</span>
          ${escapeHtml(result)}<br>
          <small>${Number(p.chips || 0)} fichas${out}</small>
        </div>
      </div>`;
    })
    .join("");

  const footer = game.gameOver
    ? '<small>O jogo terminou. Clique em reiniciar para todos voltarem com 1000 fichas.</small><button type="button" id="restartGameBtn">Reiniciar jogo</button>'
    : "<small>Próxima mão em alguns segundos...</small>";

  panel.querySelector("div").innerHTML = rows + footer;
  panel
    .querySelector("#restartGameBtn")
    ?.addEventListener("click", () => startHand(true));
}

function miniCard(c) {
  if (!c) return "";
  const cls = c.suit === "♥" || c.suit === "♦" ? "mini-card red" : "mini-card";
  return `<span class="${cls}">${escapeHtml(c.value + c.suit)}</span>`;
}

function renderLog() {
  const log = $("#actionLog");
  if (!log || !game.log) return;
  log.innerHTML = game.log
    .slice(-9)
    .reverse()
    .map((item) => `<li>${escapeHtml(item)}</li>`)
    .join("");
}
function escapeHtml(v) {
  return String(v).replace(
    /[&<>'"]/g,
    (s) =>
      ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#39;", '"': "&quot;" })[
        s
      ],
  );
}
function wait(ms) {
  return new Promise((r) => setTimeout(r, ms));
}
