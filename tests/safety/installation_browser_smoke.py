"""Isolated installer smoke test. Requires synthetic fixture, test TLS edge and Playwright 1.56.

Run only against panel.install.test:18443 and feeds.install.test:18443.
The password is read from /run/admin-password; output/screenshots go to /results.
"""
from pathlib import Path
from playwright.sync_api import sync_playwright
password = Path('/run/admin-password').read_text().strip()
base = 'https://panel.install.test:18443'
feeds = 'https://feeds.install.test:18443'
with sync_playwright() as p:
    browser = p.chromium.launch(args=['--no-proxy-server', '--host-resolver-rules=MAP panel.install.test 127.0.0.1,MAP feeds.install.test 127.0.0.1'])
    context = browser.new_context(ignore_https_errors=True, viewport={'width':1440,'height':1000})
    page = context.new_page()
    assert page.goto(base+'/login').status == 200
    page.locator('#email').fill('admin@example.test')
    page.locator('#password').fill(password)
    page.get_by_role('button', name='Entrar', exact=True).click()
    page.wait_for_url(base+'/')
    assert page.locator('h1').count() > 0
    print('Login real e dashboard: OK')
    for path in ['/empresas','/licencas','/usuarios','/anatel','/dominios','/configuracoes']:
        response=page.goto(base+path)
        assert response.status==200, (path,response.status)
        assert page.locator('h1').count()>0, path
        print(path+': HTTP 200')
    assert page.goto(base+'/assets/app.css').status==200
    assert page.goto(base+'/build/manifest.json').status==200
    response=context.request.get(feeds+'/rpz/lab-install.zone')
    assert response.status==200, response.status
    assert 'blocked.install.test' in response.text() and 'CNAME .' in response.text()
    print('Download real da zona RPZ e regra NXDOMAIN: OK')
    for path, expected in [('/rpz/negado-install.zone',403),('/rpz/inexistente.zone',404),('/login',404)]:
        response=context.request.get(feeds+path)
        assert response.status==expected, (path,response.status)
        print('Feeds '+path+': HTTP '+str(expected))
    page.set_viewport_size({'width':390,'height':844})
    page.goto(base+'/empresas')
    page.screenshot(path='/results/empresas-mobile.png',full_page=True)
    browser.close()
