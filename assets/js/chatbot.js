(function () {
  if (!window.TracsoftLeadBot) return;

  const cfg = window.TracsoftLeadBot;
  const root = document.getElementById("tracsoft-lead-bot-root");
  if (!root) return;

  const teaserCookie = "tracsoft_lead_bot_teaser_dismissed";
  let state = {
    open: false,
    sessionId: "",
    stage: "closed",
    busy: false,
    starting: false,
    teaserVisible: false,
    teaserTimer: null,
    messages: [],
    quickReplies: []
  };

  function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text) node.textContent = text;
    return node;
  }

  function renderShell() {
    root.style.setProperty("--tlb-primary", cfg.primaryColor || "#0f766e");
    root.style.setProperty("--tlb-secondary", cfg.secondaryColor || "#f97316");
    root.classList.toggle("tlb-left", cfg.position === "bottom_left");
    root.innerHTML = "";

    const teaser = el("aside", "tlb-teaser");
    teaser.hidden = !state.teaserVisible || state.open;
    teaser.setAttribute("aria-label", "Chat prompt");
    teaser.innerHTML = `
      <button type="button" class="tlb-teaser-close" aria-label="Hide chat prompt">x</button>
      <button type="button" class="tlb-teaser-message">Hey! Want help figuring out the right next step?</button>
      <button type="button" class="tlb-teaser-message">I can help point you toward websites, marketing, AI, or automation.</button>
    `;
    teaser.querySelectorAll(".tlb-teaser-message").forEach((button) => {
      button.addEventListener("click", function () {
        state.teaserVisible = false;
        state.open = true;
        renderShell();
      });
    });
    teaser.querySelector(".tlb-teaser-close").addEventListener("click", dismissTeaser);

    const bubble = el("button", "tlb-bubble");
    bubble.type = "button";
    bubble.setAttribute("aria-label", cfg.buttonLabel || "Open chat");
    bubble.setAttribute("aria-expanded", state.open ? "true" : "false");
    if (cfg.siteIconUrl) {
      const icon = el("img", "tlb-bubble-icon");
      icon.src = cfg.siteIconUrl;
      icon.alt = "";
      icon.decoding = "async";
      bubble.append(icon);
    } else {
      bubble.append(el("span", "tlb-bubble-mark", "T"));
    }
    bubble.addEventListener("click", toggle);

    const panel = el("section", "tlb-panel");
    panel.setAttribute("aria-label", cfg.title || "Tracsoft Lead Bot");
    panel.hidden = !state.open;
    panel.innerHTML = `
      <header class="tlb-header">
        <strong></strong>
        <button type="button" class="tlb-close" aria-label="Close chat">x</button>
      </header>
      <div class="tlb-messages" role="log" aria-live="polite"></div>
      <div class="tlb-quick"></div>
      <form class="tlb-contact" hidden>
        <input name="name" autocomplete="name" placeholder="Best name" required>
        <input name="company_name" autocomplete="organization" placeholder="Company name" required>
        <input name="email" autocomplete="email" type="email" placeholder="Email" required>
        <input name="phone" autocomplete="tel" placeholder="Phone" required>
        <button type="submit">Send to Alan</button>
      </form>
      <form class="tlb-input">
        <textarea name="message" autocomplete="off" rows="1" placeholder="Type your answer..." aria-label="Type your answer"></textarea>
        <button type="submit">Send</button>
      </form>
      <p class="tlb-privacy"></p>
    `;
    panel.querySelector("strong").textContent = cfg.title || "Tracsoft Lead Bot";
    panel.querySelector(".tlb-privacy").textContent = cfg.privacyNote || "";
    panel.querySelector(".tlb-close").addEventListener("click", toggle);
    panel.querySelector(".tlb-input").addEventListener("submit", submitMessage);
    panel.querySelector(".tlb-contact").addEventListener("submit", submitContact);
    const textarea = panel.querySelector(".tlb-input textarea");
    textarea.addEventListener("input", resizeComposer);
    textarea.addEventListener("keydown", function (event) {
      if (event.key === "Enter" && !event.shiftKey) {
        event.preventDefault();
        panel.querySelector(".tlb-input").requestSubmit();
      }
    });

    root.append(teaser, panel, bubble);
    renderConversation();
    syncStageControls();
    setBusy(state.busy);
    if (state.open && !state.sessionId && !state.starting) start();
    scheduleTeaser();
  }

  function toggle() {
    state.open = !state.open;
    if (state.open) state.teaserVisible = false;
    renderShell();
  }

  function scheduleTeaser() {
    if (state.open || state.teaserVisible || state.teaserTimer || getCookie(teaserCookie)) return;
    state.teaserTimer = window.setTimeout(function () {
      state.teaserTimer = null;
      if (state.open || getCookie(teaserCookie)) return;
      state.teaserVisible = true;
      renderShell();
    }, 3500);
  }

  function dismissTeaser(event) {
    if (event) event.stopPropagation();
    state.teaserVisible = false;
    setCookie(teaserCookie, "1", 1);
    renderShell();
  }

  function setCookie(name, value, days) {
    const expires = new Date(Date.now() + days * 24 * 60 * 60 * 1000).toUTCString();
    document.cookie = `${encodeURIComponent(name)}=${encodeURIComponent(value)}; expires=${expires}; path=/; SameSite=Lax`;
  }

  function getCookie(name) {
    const encoded = `${encodeURIComponent(name)}=`;
    return document.cookie.split(";").some((part) => part.trim().indexOf(encoded) === 0);
  }

  function messages() {
    return root.querySelector(".tlb-messages");
  }

  function renderConversation() {
    const area = messages();
    if (!area) return;
    area.innerHTML = "";
    state.messages.forEach((message) => appendMessage(message.role, message.text));
    renderQuickReplies();
    if (state.busy) {
      area.append(el("div", "tlb-message tlb-bot tlb-typing", "Typing..."));
    }
    area.scrollTop = area.scrollHeight;
  }

  function appendMessage(role, text) {
    const node = el("div", `tlb-message tlb-${role}`);
    text.split(/\n+/).forEach((part) => {
      if (!part.trim()) return;
      const p = el("p", "", part.trim());
      if (/^https?:\/\//.test(part.trim())) {
        const a = el("a", "", part.trim());
        a.href = part.trim();
        a.target = "_blank";
        a.rel = "noopener";
        p.textContent = "";
        p.append(a);
      }
      node.append(p);
    });
    const area = messages();
    if (!area) return;
    area.append(node);
    area.scrollTop = area.scrollHeight;
  }

  function addMessage(role, text) {
    state.messages.push({ role, text });
    appendMessage(role, text);
  }

  function setQuickReplies(replies) {
    state.quickReplies = replies || [];
    renderQuickReplies();
  }

  function renderQuickReplies() {
    const area = root.querySelector(".tlb-quick");
    if (!area) return;
    area.innerHTML = "";
    state.quickReplies.forEach((reply) => {
      const button = el("button", "tlb-chip", reply);
      button.type = "button";
      button.addEventListener("click", () => send(reply, reply));
      area.append(button);
    });
  }

  function setBusy(busy) {
    state.busy = busy;
    root.querySelectorAll("button,input,textarea").forEach((node) => {
      if (!node.classList.contains("tlb-close") && !node.classList.contains("tlb-bubble")) node.disabled = busy;
    });
    let typing = root.querySelector(".tlb-typing");
    if (busy && !typing) {
      typing = el("div", "tlb-message tlb-bot tlb-typing", "Typing...");
      messages().append(typing);
    } else if (!busy && typing) {
      typing.remove();
    }
  }

  function syncStageControls() {
    const contactForm = root.querySelector(".tlb-contact");
    const inputForm = root.querySelector(".tlb-input");
    if (!contactForm || !inputForm) return;
    contactForm.hidden = state.stage !== "hot_contact_collection";
    inputForm.hidden = state.stage === "hot_contact_collection" || state.stage === "routed";
  }

  async function start() {
    state.starting = true;
    setBusy(true);
    try {
      const res = await fetch(`${cfg.restUrl}/start`, { method: "POST", headers: { "Content-Type": "application/json" } });
      const data = await res.json();
      state.sessionId = data.session_id;
      state.stage = data.conversation_stage;
      addMessage("bot", data.bot_reply);
      setQuickReplies(data.quick_replies);
    } catch (e) {
      addMessage("bot", "I'm sorry, I'm having trouble responding right now. The best next step is to schedule a consultation with Tracsoft here: https://tracsoft.com/consultation/");
    } finally {
      state.starting = false;
      setBusy(false);
    }
  }

  function submitMessage(event) {
    event.preventDefault();
    const input = event.currentTarget.elements.message;
    const value = input.value.trim();
    if (!value || state.busy) return;
    input.value = "";
    resizeComposer({ currentTarget: input });
    send(value, "");
  }

  function resizeComposer(event) {
    const input = event.currentTarget;
    input.style.height = "auto";
    input.style.height = `${Math.min(input.scrollHeight, 108)}px`;
  }

  async function send(message, selectedOption) {
    if (!state.sessionId || state.busy) return;
    addMessage("user", message);
    setQuickReplies([]);
    setBusy(true);
    try {
      const res = await fetch(`${cfg.restUrl}/message`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ session_id: state.sessionId, message, selected_option: selectedOption || "" })
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.message || "Request failed");
      state.stage = data.conversation_stage;
      addMessage("bot", data.bot_reply);
      setQuickReplies(data.quick_replies);
      syncStageControls();
    } catch (e) {
      addMessage("bot", "I'm sorry, I'm having trouble responding right now. The best next step is to schedule a consultation with Tracsoft here: https://tracsoft.com/consultation/");
    } finally {
      setBusy(false);
    }
  }

  async function submitContact(event) {
    event.preventDefault();
    if (state.busy) return;
    const form = event.currentTarget;
    const payload = Object.fromEntries(new FormData(form).entries());
    payload.session_id = state.sessionId;
    setBusy(true);
    try {
      const res = await fetch(`${cfg.restUrl}/hot-lead-contact`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.message || "Could not submit");
      addMessage("bot", data.message);
      state.stage = "routed";
      form.hidden = true;
      syncStageControls();
    } catch (e) {
      addMessage("bot", e.message || "Please check the contact details and try again.");
    } finally {
      setBusy(false);
    }
  }

  renderShell();
})();
