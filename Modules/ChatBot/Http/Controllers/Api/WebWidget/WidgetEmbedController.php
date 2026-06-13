<?php

namespace Modules\ChatBot\Http\Controllers\Api\WebWidget;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\ChatBot\Models\Channel;

class WidgetEmbedController extends Controller
{
    public function __invoke(Request $request, string $key): Response
    {
        $channel = Channel::where('type', 'web_widget')
            ->where('public_key', $key)
            ->first();

        if (! $channel || ! $channel->enabled || empty($channel->allowed_domain)) {
            return response('// widget_disabled', 200, [
                'Content-Type' => 'application/javascript; charset=utf-8',
                'Cache-Control' => 'no-store',
            ]);
        }

        $origin = $this->extractHost($request->header('Origin') ?? $request->header('Referer') ?? '');
        if ($origin !== '' && ! $this->domainMatches($origin, $channel->allowed_domain)) {
            return response('// domain_not_allowed', 200, [
                'Content-Type' => 'application/javascript; charset=utf-8',
                'Cache-Control' => 'no-store',
            ]);
        }

        $settings = $channel->settings ?? [];
        $config = [
            'key' => $channel->public_key,
            'name' => $channel->name,
            'title' => $settings['title'] ?? 'Asistente virtual',
            'subtitle' => $settings['subtitle'] ?? 'Te respondemos en minutos',
            'greeting' => $settings['greeting'] ?? '¡Hola! ¿En qué podemos ayudarte?',
            'position' => $settings['position'] ?? 'right',
            'show_typing' => (bool) ($settings['show_typing'] ?? true),
            'offline_message' => $settings['offline_message'] ?? null,
            'webhook_url' => $channel->webhookUrl(),
        ];

        $configJson = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $js = $this->buildWidgetScript($configJson);

        return response($js, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    private function domainMatches(string $origin, string $allowed): bool
    {
        $allowed = strtolower(trim($allowed));
        $origin = strtolower($origin);

        if (str_starts_with($allowed, '*.')) {
            $suffix = substr($allowed, 1);

            return str_ends_with($origin, $suffix);
        }

        return $origin === $allowed;
    }

    private function extractHost(string $url): string
    {
        if ($url === '') {
            return '';
        }
        $host = parse_url($url, PHP_URL_HOST);
        if (is_string($host) && $host !== '') {
            return $host;
        }

        return preg_replace('#^https?://#i', '', $url) ?? '';
    }

    private function buildWidgetScript(string $configJson): string
    {
        return <<<JS
(function () {
  'use strict';
  var CFG = {$configJson};
  if (!CFG || !CFG.webhook_url) { return; }

  function getCurrentScript() {
    if (document.currentScript) return document.currentScript;
    var scripts = document.getElementsByTagName('script');
    return scripts[scripts.length - 1] || null;
  }

  function onReady(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  function el(tag, attrs, children) {
    var node = document.createElement(tag);
    if (attrs) {
      for (var k in attrs) {
        if (k === 'style' && typeof attrs[k] === 'object') {
          for (var s in attrs[k]) { node.style[s] = attrs[k][s]; }
        } else if (k === 'class') {
          node.className = attrs[k];
        } else if (k === 'text') {
          node.textContent = attrs[k];
        } else if (k.indexOf('on') === 0 && typeof attrs[k] === 'function') {
          node.addEventListener(k.slice(2).toLowerCase(), attrs[k]);
        } else if (attrs[k] === true) {
          node.setAttribute(k, '');
        } else if (attrs[k] !== false && attrs[k] != null) {
          node.setAttribute(k, attrs[k]);
        }
      }
    }
    if (children) {
      for (var i = 0; i < children.length; i++) {
        if (children[i]) { node.appendChild(children[i]); }
      }
    }
    return node;
  }

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  var styleInjected = false;
  function injectStyles() {
    if (styleInjected) return;
    styleInjected = true;
    var pos = CFG.position === 'left' ? 'left' : 'right';
    var css = ''
      + '#hb-chatbot-root{position:fixed;bottom:16px;' + pos + ':16px;z-index:2147483000;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;}'
      + '#hb-chatbot-btn{width:56px;height:56px;border-radius:50%;background:#2563eb;color:#fff;border:none;box-shadow:0 10px 25px rgba(0,0,0,.2);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:24px;transition:transform .2s;}'
      + '#hb-chatbot-btn:hover{transform:scale(1.05);}'
      + '#hb-chatbot-panel{position:absolute;bottom:72px;' + pos + ':0;width:360px;max-width:calc(100vw - 32px);height:520px;max-height:calc(100vh - 100px);background:#fff;border-radius:12px;box-shadow:0 20px 50px rgba(0,0,0,.25);display:none;flex-direction:column;overflow:hidden;border:1px solid #e5e7eb;}'
      + '#hb-chatbot-panel.open{display:flex;}'
      + '#hb-chatbot-header{background:#2563eb;color:#fff;padding:14px 16px;display:flex;align-items:center;justify-content:space-between;}'
      + '#hb-chatbot-header h3{margin:0;font-size:15px;font-weight:600;}'
      + '#hb-chatbot-header p{margin:2px 0 0;font-size:12px;opacity:.85;}'
      + '#hb-chatbot-close{background:transparent;border:none;color:#fff;cursor:pointer;font-size:20px;line-height:1;padding:0 4px;}'
      + '#hb-chatbot-messages{flex:1;overflow-y:auto;padding:16px;background:#f9fafb;display:flex;flex-direction:column;gap:8px;}'
      + '#hb-chatbot-messages .hb-msg{max-width:80%;padding:8px 12px;border-radius:12px;font-size:14px;line-height:1.4;word-wrap:break-word;}'
      + '#hb-chatbot-messages .hb-msg.bot{background:#fff;color:#111827;border:1px solid #e5e7eb;align-self:flex-start;border-bottom-left-radius:4px;}'
      + '#hb-chatbot-messages .hb-msg.user{background:#2563eb;color:#fff;align-self:flex-end;border-bottom-right-radius:4px;}'
      + '#hb-chatbot-messages .hb-msg.system{background:#fef3c7;color:#92400e;align-self:center;font-size:12px;padding:4px 10px;}'
      + '#hb-chatbot-messages .hb-msg.error{background:#fee2e2;color:#991b1b;align-self:center;font-size:12px;padding:6px 10px;}'
      + '#hb-chatbot-typing{align-self:flex-start;background:#fff;border:1px solid #e5e7eb;padding:8px 12px;border-radius:12px;border-bottom-left-radius:4px;font-size:12px;color:#6b7280;display:none;}'
      + '#hb-chatbot-typing span{display:inline-block;width:6px;height:6px;background:#6b7280;border-radius:50%;margin:0 2px;animation:hb-typing 1.4s infinite;}'
      + '#hb-chatbot-typing span:nth-child(2){animation-delay:.2s;}'
      + '#hb-chatbot-typing span:nth-child(3){animation-delay:.4s;}'
      + '@keyframes hb-typing{0%,60%,100%{transform:translateY(0);opacity:.3;}30%{transform:translateY(-4px);opacity:1;}}'
      + '#hb-chatbot-form{padding:12px;border-top:1px solid #e5e7eb;background:#fff;display:flex;flex-direction:column;gap:8px;}'
      + '#hb-chatbot-form .hb-row{display:flex;gap:6px;}'
      + '#hb-chatbot-form input,#hb-chatbot-form textarea{flex:1;border:1px solid #d1d5db;border-radius:8px;padding:8px 10px;font-size:13px;font-family:inherit;outline:none;resize:none;}'
      + '#hb-chatbot-form input:focus,#hb-chatbot-form textarea:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.15);}'
      + '#hb-chatbot-form textarea{height:60px;}'
      + '#hb-chatbot-form button[type=submit]{background:#2563eb;color:#fff;border:none;border-radius:8px;padding:0 14px;font-size:13px;font-weight:600;cursor:pointer;}'
      + '#hb-chatbot-form button[type=submit]:disabled{background:#9ca3af;cursor:not-allowed;}'
      + '#hb-chatbot-form .hb-lead{padding:8px;background:#f3f4f6;border-radius:8px;display:flex;flex-direction:column;gap:6px;}'
      + '#hb-chatbot-form .hb-lead p{margin:0;font-size:12px;color:#6b7280;}'
      + '@media(max-width:480px){#hb-chatbot-panel{width:calc(100vw - 32px);}}'
    ;
    var style = document.createElement('style');
    style.setAttribute('data-hb-chatbot', '');
    style.textContent = css;
    document.head.appendChild(style);
  }

  function addMsg(text, kind) {
    var msgs = document.getElementById('hb-chatbot-messages');
    if (!msgs) return;
    var div = document.createElement('div');
    div.className = 'hb-msg ' + (kind || 'bot');
    div.textContent = text;
    msgs.appendChild(div);
    msgs.scrollTop = msgs.scrollHeight;
  }

  function setTyping(show) {
    var t = document.getElementById('hb-chatbot-typing');
    if (t) t.style.display = show ? 'block' : 'none';
  }

  function render(state) {
    injectStyles();

    var existing = document.getElementById('hb-chatbot-root');
    if (existing) existing.parentNode.removeChild(existing);

    var root = el('div', { id: 'hb-chatbot-root' });

    var btn = el('button', {
      id: 'hb-chatbot-btn',
      type: 'button',
      'aria-label': 'Abrir chat',
      title: CFG.title,
      onclick: function () { togglePanel(); }
    });
    btn.innerHTML = '&#x1F4AC;';
    root.appendChild(btn);

    var panel = el('div', { id: 'hb-chatbot-panel' });

    var header = el('div', { id: 'hb-chatbot-header' }, [
      el('div', null, [
        el('h3', { text: CFG.title }),
        CFG.subtitle ? el('p', { text: CFG.subtitle }) : null,
      ]),
      el('button', {
        id: 'hb-chatbot-close',
        type: 'button',
        'aria-label': 'Cerrar',
        onclick: function () { togglePanel(); }
      }, [document.createTextNode('\u00d7')]),
    ]);
    panel.appendChild(header);

    var msgs = el('div', { id: 'hb-chatbot-messages' });
    panel.appendChild(msgs);

    var typing = el('div', { id: 'hb-chatbot-typing' }, [
      document.createTextNode('Escribiendo'),
      el('span'),
      el('span'),
      el('span'),
    ]);
    panel.appendChild(typing);

    var form = renderForm(state);
    panel.appendChild(form);

    root.appendChild(panel);
    document.body.appendChild(root);

    if (state.open) panel.classList.add('open');
  }

  function renderForm(state) {
    var form = el('form', {
      id: 'hb-chatbot-form',
      onsubmit: function (e) {
        e.preventDefault();
        onSend(state);
      }
    });

    if (!state.lead) {
      var lead = el('div', { class: 'hb-lead' });
      lead.appendChild(el('p', { text: 'Por favor dinos tu nombre y email para comenzar:' }));
      var row1 = el('div', { class: 'hb-row' });
      var nameI = el('input', {
        type: 'text', name: 'name', placeholder: 'Tu nombre', required: 'required', maxlength: '255',
        value: state.visitor.name || ''
      });
      var emailI = el('input', {
        type: 'email', name: 'email', placeholder: 'tu@email.com', required: 'required', maxlength: '255',
        value: state.visitor.email || ''
      });
      row1.appendChild(nameI);
      row1.appendChild(emailI);
      lead.appendChild(row1);
      form.appendChild(lead);
      form._nameInput = nameI;
      form._emailInput = emailI;
    }

    var textarea = el('textarea', {
      name: 'message', placeholder: 'Escribe tu mensaje...', required: 'required', maxlength: '5000'
    });
    textarea.value = '';
    form.appendChild(textarea);

    var row = el('div', { class: 'hb-row' });
    var submit = el('button', { type: 'submit' });
    submit.textContent = 'Enviar';
    row.appendChild(textarea);
    var sendBtnWrap = el('div', { style: { display: 'flex' } });
    sendBtnWrap.appendChild(submit);
    row.appendChild(sendBtnWrap);
    form.appendChild(row);

    form._submit = submit;
    form._textarea = textarea;

    return form;
  }

  function togglePanel() {
    var panel = document.getElementById('hb-chatbot-panel');
    var btn = document.getElementById('hb-chatbot-btn');
    if (!panel) return;
    if (panel.classList.contains('open')) {
      panel.classList.remove('open');
      if (btn) btn.style.display = 'flex';
    } else {
      panel.classList.add('open');
      if (btn) btn.style.display = 'none';
      var msgs = document.getElementById('hb-chatbot-messages');
      if (msgs && msgs.childNodes.length === 0) {
        if (CFG.greeting) addMsg(CFG.greeting, 'bot');
      }
      setTimeout(function () {
        var form = document.getElementById('hb-chatbot-form');
        if (form) {
          var f = form.querySelector('input[name=name]') || form.querySelector('textarea[name=message]');
          if (f) f.focus();
        }
      }, 50);
    }
  }

  function onSend(state) {
    var form = document.getElementById('hb-chatbot-form');
    if (!form) return;
    var submit = form._submit;
    var textarea = form._textarea;

    var name = form._nameInput ? form._nameInput.value.trim() : state.visitor.name;
    var email = form._emailInput ? form._emailInput.value.trim() : state.visitor.email;

    var msg = textarea.value.trim();
    if (!msg) return;
    if (form._nameInput && !name) { form._nameInput.focus(); return; }
    if (form._emailInput && !email) { form._emailInput.focus(); return; }
    if (form._nameInput && !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) {
      addMsg('Por favor ingresa un email v\u00e1lido.', 'error');
      return;
    }

    state.visitor.name = name;
    state.visitor.email = email;
    state.lead = true;

    addMsg(msg, 'user');
    textarea.value = '';
    submit.disabled = true;
    if (CFG.show_typing) setTyping(true);

    var payload = {
      visitor: { name: name, email: email },
      message: { content: msg }
    };

    fetch(CFG.webhook_url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
      credentials: 'omit'
    })
    .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, status: r.status, body: j }; }); })
    .then(function (res) {
      if (CFG.show_typing) setTyping(false);
      submit.disabled = false;
      if (res.ok && res.body.ok) {
        addMsg('\u00a1Gracias! Te responderemos pronto.', 'system');
      } else if (res.body && res.body.errors) {
        var first = Object.keys(res.body.errors)[0];
        addMsg('Error: ' + (Array.isArray(res.body.errors[first]) ? res.body.errors[first][0] : res.body.errors[first]), 'error');
      } else {
        addMsg('No se pudo enviar. Intenta de nuevo.', 'error');
      }
    })
    .catch(function () {
      if (CFG.show_typing) setTyping(false);
      submit.disabled = false;
      addMsg('Error de conexi\u00f3n. Intenta de nuevo.', 'error');
    });
  }

  onReady(function () {
    var state = { open: false, lead: false, visitor: { name: '', email: '' } };
    render(state);
  });
})();
JS;
    }
}
