import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
function worker(offline = false, financial = false) {
    const handlers = {}, stored = [];
    const cache = { match: async () => null, put: async (request) => stored.push(request.url), keys: async () => [], addAll: async () => {}, delete: async () => {} };
    runInNewContext(readFileSync('public/sw.js', 'utf8'), {
        URL, Request, Promise,
        self: { location: { origin: 'https://saldoup.test' }, addEventListener: (event, fn) => handlers[event] = fn },
        caches: { open: async () => cache, match: async () => new Response('offline público') },
        fetch: async request => { if (offline) throw new Error('offline'); const path = new URL(request.url).pathname; const type = financial ? 'application/json' : path.endsWith('.css') ? 'text/css' : path.endsWith('.webmanifest') ? 'application/manifest+json' : path.endsWith('.html') ? 'text/html' : 'image/png'; return new Response('conteúdo de rede', { headers: { 'Content-Type': type } }); },
    });
    return { install() { let promise; handlers.install({ waitUntil: value => promise = value }); return promise; }, dispatch(path, mode = 'cors', method = 'GET') {
        let intercepted;
        const request = new Request(`https://saldoup.test${path}`, { method });
        Object.defineProperty(request, 'mode', { value: mode });
        handlers.fetch({ request, respondWith: promise => intercepted = promise });
        return intercepted;
    }, stored };
}
test('páginas pessoais nunca entram no cache, mesmo após resposta bem-sucedida', async () => {
    const w = worker();
    for (const path of ['/painel', '/financas', '/administracao/usuarios', '/convites/segredo']) {
        const response = await w.dispatch(path, 'navigate'); assert.equal(await response.text(), 'conteúdo de rede');
    }
    assert.deepEqual(w.stored, []);
});
test('POST, respostas Livewire e links com token não são interceptados como assets', () => {
    const w = worker();
    assert.equal(w.dispatch('/financas/lancamentos', 'cors', 'POST'), undefined);
    assert.equal(w.dispatch('/livewire/update'), undefined);
    assert.equal(w.dispatch('/build/assets/app.css?token=segredo'), undefined);
    assert.equal(w.dispatch('/extrato.pdf'), undefined);
});
test('quando offline, navegação recebe somente a página pública de indisponibilidade', async () => {
    const w = worker(true); const response = await w.dispatch('/financas', 'navigate');
    assert.equal(await response.text(), 'offline público'); assert.deepEqual(w.stored, []);
});

test('somente asset público permitido é gravado no cache', async () => {
    const w = worker(); await w.dispatch('/build/assets/app-AbCd1234.css');
    assert.deepEqual(w.stored, ['https://saldoup.test/build/assets/app-AbCd1234.css']);
});

test('resposta financeira não é armazenada mesmo se houver erro de rota em URL de asset', async () => {
    const w = worker(false, true); await w.dispatch('/build/assets/app-AbCd1234.css');
    assert.deepEqual(w.stored, []);
});
test('instalação rejeita resposta financeira no lugar do shell público', async () => {
    const w = worker(false, true); await assert.rejects(w.install());
    assert.deepEqual(w.stored, []);
});
