{{-- Telegram Syndicate Link — Cyberpunk Neon Panel --}}
<style>
    @keyframes neonPulse {
        0%, 100% { box-shadow: 0 0 8px rgba(0, 255, 170, 0.4), 0 0 20px rgba(0, 255, 170, 0.15), inset 0 0 12px rgba(0, 255, 170, 0.03); }
        50%      { box-shadow: 0 0 14px rgba(0, 255, 170, 0.6), 0 0 35px rgba(0, 255, 170, 0.25), inset 0 0 18px rgba(0, 255, 170, 0.05); }
    }
    @keyframes scanline {
        0%   { background-position: 0 0; }
        100% { background-position: 0 100%; }
    }
    .tg-syndicate {
        position: relative;
        border: 1px solid rgba(0, 255, 170, 0.3);
        border-top: 3px solid;
        border-image: linear-gradient(90deg, #00ffaa, #00e5ff, #00ffaa) 1;
        border-radius: 6px;
        background: linear-gradient(180deg, rgba(0, 255, 170, 0.04) 0%, rgba(10, 14, 20, 0.95) 8%);
        animation: neonPulse 3s ease-in-out infinite;
        margin-top: 24px;
        overflow: hidden;
    }
    .tg-syndicate::before {
        content: '';
        position: absolute;
        inset: 0;
        background: repeating-linear-gradient(0deg, transparent, transparent 2px, rgba(0, 255, 170, 0.015) 2px, rgba(0, 255, 170, 0.015) 4px);
        background-size: 100% 4px;
        animation: scanline 8s linear infinite;
        pointer-events: none;
        z-index: 0;
    }
    .tg-syndicate > * { position: relative; z-index: 1; }

    .tg-heading {
        padding: 14px 20px;
        margin: 0;
        font-size: 1.1rem;
        font-weight: 700;
        letter-spacing: 1.5px;
        text-transform: uppercase;
        color: #00ffaa;
        text-shadow: 0 0 6px rgba(0, 255, 170, 0.5), 0 0 14px rgba(0, 255, 170, 0.2);
        border-bottom: 1px solid rgba(0, 255, 170, 0.15);
        background: rgba(0, 255, 170, 0.03);
    }
    .tg-heading span { filter: brightness(1.4); }

    .tg-console {
        background: rgba(5, 8, 12, 0.8);
        border: 1px solid rgba(0, 255, 170, 0.1);
        border-radius: 4px;
        padding: 16px 18px;
        margin: 16px;
        font-family: 'Fira Code', 'JetBrains Mono', 'Cascadia Code', monospace;
        font-size: 0.88rem;
        line-height: 1.7;
    }

    .tg-status-line {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 8px;
    }
    .tg-status-icon {
        font-size: 1.1rem;
        filter: drop-shadow(0 0 4px rgba(0, 255, 170, 0.6));
    }
    .tg-status-label {
        color: #00ffaa;
        font-weight: 700;
        text-shadow: 0 0 4px rgba(0, 255, 170, 0.3);
        letter-spacing: 0.5px;
    }
    .tg-status-text {
        color: rgba(255, 255, 255, 0.55);
        font-size: 0.84rem;
        margin-left: 28px;
    }

    .tg-token-row {
        display: flex;
        gap: 8px;
        align-items: stretch;
        margin-bottom: 6px;
    }
    .tg-token-field {
        flex: 1;
        background: rgba(0, 0, 0, 0.5) !important;
        border: 1px solid rgba(0, 255, 170, 0.25) !important;
        color: #00ffaa !important;
        font-family: 'Fira Code', 'JetBrains Mono', monospace !important;
        font-size: 0.9rem !important;
        letter-spacing: 1px;
        padding: 10px 14px !important;
        text-shadow: 0 0 3px rgba(0, 255, 170, 0.2);
    }
    .tg-token-field:focus {
        border-color: #00ffaa !important;
        box-shadow: 0 0 8px rgba(0, 255, 170, 0.3) !important;
    }

    .tg-btn-copy {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 10px 16px;
        background: rgba(0, 229, 255, 0.1);
        border: 1px solid rgba(0, 229, 255, 0.35);
        color: #00e5ff;
        font-weight: 600;
        font-size: 0.85rem;
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.25s ease;
        white-space: nowrap;
    }
    .tg-btn-copy:hover {
        background: rgba(0, 229, 255, 0.25);
        box-shadow: 0 0 12px rgba(0, 229, 255, 0.4);
        color: #fff;
    }
    .tg-btn-copy.tg-copied {
        background: rgba(0, 255, 170, 0.2);
        border-color: #00ffaa;
        color: #00ffaa;
    }

    .tg-btn-link {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 12px 28px;
        background: linear-gradient(135deg, rgba(0, 255, 170, 0.2), rgba(0, 229, 255, 0.2));
        border: 2px solid rgba(0, 255, 170, 0.5);
        color: #00ffaa;
        font-weight: 700;
        font-size: 1rem;
        letter-spacing: 1px;
        border-radius: 4px;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.25s ease;
        text-shadow: 0 0 3px rgba(0, 255, 170, 0.3);
        text-transform: uppercase;
    }
    .tg-btn-link:hover {
        background: linear-gradient(135deg, rgba(0, 255, 170, 0.35), rgba(0, 229, 255, 0.35));
        box-shadow: 0 0 20px rgba(0, 255, 170, 0.5), 0 0 40px rgba(0, 255, 170, 0.2);
        color: #fff;
        text-shadow: 0 0 8px rgba(0, 255, 170, 0.7);
        transform: translateY(-2px);
    }

    .tg-btn-danger {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 22px;
        background: rgba(255, 50, 50, 0.08);
        border: 1px solid rgba(255, 50, 50, 0.35);
        color: #ff4444;
        font-weight: 600;
        font-size: 0.9rem;
        letter-spacing: 0.5px;
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.25s ease;
    }
    .tg-btn-danger:hover {
        background: rgba(255, 50, 50, 0.2);
        border-color: #ff4444;
        box-shadow: 0 0 16px rgba(255, 50, 50, 0.4), 0 0 30px rgba(255, 50, 50, 0.15);
        color: #ff6666;
        text-shadow: 0 0 6px rgba(255, 50, 50, 0.5);
        transform: translateY(-1px);
    }

    .tg-actions {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        align-items: center;
        margin-top: 14px;
    }
    .tg-hint {
        color: rgba(255, 255, 255, 0.4);
        font-size: 0.82rem;
        margin-top: 12px;
        font-style: italic;
    }
    .tg-hint code {
        color: #00e5ff;
        background: rgba(0, 229, 255, 0.08);
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 0.82rem;
    }
    .tg-botfather {
        margin-top: 16px;
        padding: 12px 16px;
        background: rgba(0, 229, 255, 0.04);
        border: 1px dashed rgba(0, 229, 255, 0.2);
        border-radius: 4px;
        color: rgba(255, 255, 255, 0.5);
        font-size: 0.82rem;
    }
    .tg-botfather code {
        color: #00e5ff;
        background: rgba(0, 229, 255, 0.08);
        padding: 1px 5px;
        border-radius: 3px;
    }
</style>

<div class="tg-syndicate">
    <h2 class="tg-heading"><span>🔌</span> Telegram Syndicate Link</h2>

    <div class="tg-console">
        @if (Auth::user()->telegram_chat_id)
            <div class="tg-status-line">
                <span class="tg-status-icon">🟢</span>
                <span class="tg-status-label">ENLACE ACTIVO — Cuenta Vinculada</span>
            </div>
            <div class="tg-status-text">
                Conexión establecida con el bot de Nuclear Order. Canal seguro operativo.
            </div>
            <div class="tg-actions">
                <form method="POST" action="{{ route('users.telegram.reset', ['user' => $user]) }}" style="display: inline;">
                    @csrf
                    <button
                        type="submit"
                        class="tg-btn-danger"
                        onclick="return confirm('⚠️ ¿Regenerar token?\n\nPerderás la vinculación actual y tendrás que vincular tu cuenta de nuevo con el bot.')"
                    >
                        <span>🔄</span> Regenerar Token (Desvincular)
                    </button>
                </form>
            </div>
        @else
            <div class="tg-status-line">
                <span class="tg-status-icon">⚡</span>
                <span class="tg-status-label">PENDIENTE — Vinculación Requerida</span>
            </div>
            <div class="tg-status-text" style="margin-bottom: 14px;">
                Vincula tu cuenta con el bot de Telegram para recibir notificaciones de nuevos torrents.
            </div>

            @if (Auth::user()->telegram_token)
                {{-- Token row with copy button --}}
                <div class="tg-token-row">
                    <input
                        id="tg-token-input"
                        class="form__text tg-token-field"
                        type="text"
                        value="{{ Auth::user()->telegram_token }}"
                        readonly
                    />
                    <button
                        type="button"
                        class="tg-btn-copy"
                        id="tg-copy-btn"
                        onclick="tgCopyToken()"
                    >
                        📋 Copiar
                    </button>
                </div>

                <div class="tg-hint">
                    Paso 1: Copia el token. Paso 2: Pulsa el botón de vincular. El bot hará el resto.
                </div>

                <div class="tg-actions">
                    <a
                        href="https://t.me/{{ config('services.telegram.bot_username') }}?start={{ Auth::user()->telegram_token }}"
                        class="tg-btn-link"
                        target="_blank"
                        rel="noopener"
                    >
                        🚀 VINCULAR CON EL BOT
                    </a>
                    <form method="POST" action="{{ route('users.telegram.reset', ['user' => $user]) }}" style="display: inline;">
                        @csrf
                        <button
                            type="submit"
                            class="tg-btn-danger"
                            onclick="return confirm('⚠️ ¿Regenerar token?\n\nSi ya iniciaste la vinculación, tendrás que empezar de nuevo.')"
                        >
                            <span>🔄</span> Regenerar Token
                        </button>
                    </form>
                </div>
            @else
                <div class="tg-hint" style="color: #ff9944;">
                    ⚠️ No tienes un token asignado. Pulsa "Regenerar Token" para generar uno.
                </div>
                <div class="tg-actions">
                    <form method="POST" action="{{ route('users.telegram.reset', ['user' => $user]) }}" style="display: inline;">
                        @csrf
                        <button type="submit" class="tg-btn-link">
                            <span>🔑</span> Generar Token
                        </button>
                    </form>
                </div>
            @endif

            <div class="tg-botfather">
                <strong>Comandos del Bot:</strong>
                <code>/start</code> — Vincular cuenta &nbsp;|&nbsp;
                <code>/status</code> — Ver estado del enlace &nbsp;|&nbsp;
                <code>/help</code> — Ayuda
            </div>
        @endif
    </div>
</div>

<script>
function tgCopyToken() {
    const input = document.getElementById('tg-token-input');
    const btn = document.getElementById('tg-copy-btn');
    if (!input) return;

    navigator.clipboard.writeText(input.value).then(function() {
        btn.textContent = '✅ Copiado';
        btn.classList.add('tg-copied');
        setTimeout(function() {
            btn.textContent = '📋 Copiar';
            btn.classList.remove('tg-copied');
        }, 2000);
    }).catch(function() {
        input.select();
        document.execCommand('copy');
        btn.textContent = '✅ Copiado';
        setTimeout(function() { btn.textContent = '📋 Copiar'; }, 2000);
    });
}
</script>
