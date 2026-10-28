import {
    Chart, LineController, LineElement, PointElement, LinearScale, CategoryScale,
    BarController, BarElement, DoughnutController, ArcElement, ScatterController, Tooltip, Legend, Filler, Title,
} from 'chart.js';

Chart.register(
    LineController, LineElement, PointElement, LinearScale, CategoryScale,
    BarController, BarElement, DoughnutController, ArcElement, ScatterController, Tooltip, Legend, Filler, Title,
);
Chart.defaults.font.family = 'ui-sans-serif, system-ui, "Segoe UI", Roboto, sans-serif';
Chart.defaults.font.size = 13;
Chart.defaults.color = '#475569'; // slate-600: rótulos dos eixos com contraste ≥ 4,5:1
Chart.defaults.maintainAspectRatio = false;
Chart.defaults.locale = 'pt-BR';
if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) Chart.defaults.animation = false;

window.Chart = Chart;

const fmt = (v, casas = 2) => (v === null || v === undefined ? '—' : Number(v).toLocaleString('pt-BR', { maximumFractionDigits: casas }));

/** Descreve um gráfico para leitores de tela (o canvas sozinho não é lido). */
window.descreverGrafico = (el, texto) => {
    if (!el) return;
    el.setAttribute('role', 'img');
    el.setAttribute('aria-label', texto);
};

/**
 * Gráfico de linha com faixa nominal opcional (plugin inline) e linhas de limite.
 * cfg: { id, rotulos, valores, cor, faixa: [min,max], limites: [n], titulo }
 */
window.graficoLinha = (cfg) => {
    const el = document.getElementById(cfg.id);
    if (!el) return;
    const faixa = {
        id: 'faixa',
        beforeDatasetsDraw(chart) {
            const { ctx, chartArea: a, scales: { y } } = chart;
            if (cfg.faixa) {
                const y0 = y.getPixelForValue(cfg.faixa[1]);
                const y1 = y.getPixelForValue(cfg.faixa[0]);
                ctx.save();
                ctx.fillStyle = 'rgba(46,158,91,.14)';
                ctx.fillRect(a.left, Math.max(y0, a.top), a.right - a.left, Math.min(y1, a.bottom) - Math.max(y0, a.top));
                ctx.restore();
            }
            (cfg.limites || []).forEach((v) => {
                const py = y.getPixelForValue(v);
                if (py < a.top || py > a.bottom) return;
                ctx.save();
                ctx.strokeStyle = '#d64545';
                ctx.setLineDash([5, 4]);
                ctx.beginPath(); ctx.moveTo(a.left, py); ctx.lineTo(a.right, py); ctx.stroke();
                ctx.restore();
            });
        },
    };
    const validos = cfg.valores.filter((v) => v !== null && v !== undefined);
    const todos = [...validos, ...(cfg.faixa || []), ...(cfg.limites || [])];
    const min = Math.min(...todos), max = Math.max(...todos), pad = (max - min) * 0.1 || 1;
    window.descreverGrafico(el, validos.length
        ? `${cfg.titulo}: ${validos.length} leituras, de ${fmt(Math.min(...validos))} a ${fmt(Math.max(...validos))}; última ${fmt(validos.at(-1))}.`
          + (cfg.faixa ? ` Faixa nominal ${fmt(cfg.faixa[0])} a ${fmt(cfg.faixa[1])}.` : '')
        : `${cfg.titulo}: sem leituras.`);
    new Chart(el, {
        type: 'line',
        // spanGaps: leituras sem a grandeza (ex.: sensor só de corrente) não quebram a linha.
        data: { labels: cfg.rotulos, datasets: [{ data: cfg.valores, borderColor: cfg.cor, backgroundColor: cfg.cor, tension: 0.2, spanGaps: true, pointRadius: cfg.valores.length > 40 ? 0 : 3 }] },
        options: {
            plugins: { legend: { display: false }, title: { display: true, text: cfg.titulo, align: 'start', color: '#0f172a', font: { size: 14, weight: '600' } } },
            scales: { y: { min: min - pad, max: max + pad }, x: { ticks: { maxTicksLimit: 8 } } },
        },
        plugins: [faixa],
    });
};

/**
 * Envio de formulário: o botão fica "Registrando…" e bloqueado até a resposta, para um toque duplo
 * no tablet não gravar a mesma leitura duas vezes. Respeita confirmações (onsubmit="return confirm(...)").
 */
document.addEventListener('submit', (e) => {
    if (e.defaultPrevented || e.target.method?.toLowerCase() === 'get') return;
    const botao = e.submitter || e.target.querySelector('button[type="submit"]');
    if (!botao) return;
    if (botao.dataset.enviando) { e.preventDefault(); return; }
    botao.dataset.enviando = '1';
    botao.dataset.textoOriginal = botao.innerHTML;
    botao.setAttribute('aria-busy', 'true');
    botao.innerHTML = `<svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" opacity=".3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>${botao.dataset.enviandoTexto || 'Enviando…'}`;
    setTimeout(() => { botao.disabled = true; }, 0); // depois do envio, para o valor do botão seguir junto
});
// Voltar pelo navegador (cache de página) reabilita os botões.
window.addEventListener('pageshow', () => document.querySelectorAll('button[data-enviando]').forEach((b) => {
    b.disabled = false;
    b.removeAttribute('aria-busy');
    b.innerHTML = b.dataset.textoOriginal;
    delete b.dataset.enviando;
}));

/** Depois de um envio com erros, leva o foco ao resumo para quem usa teclado ou leitor de tela. */
document.addEventListener('DOMContentLoaded', () => document.getElementById('resumo-erros')?.focus());
