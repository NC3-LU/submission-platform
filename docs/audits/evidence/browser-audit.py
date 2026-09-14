import json,time
from pathlib import Path
from playwright.sync_api import sync_playwright
base=Path('/tmp/submission-platform-audit-2026-09-14'); seed=json.loads((base/'seed.json').read_text()); results=[]
with sync_playwright() as p:
 browser=p.chromium.launch(headless=True, executable_path="/home/ppa/.cache/ms-playwright/chromium-1234/chrome-linux64/chrome")
 page=browser.new_page(viewport={'width':1440,'height':1000})
 errors=[]
 page.on('pageerror',lambda e:errors.append(str(e)))
 def inspect(name,path):
  response=page.goto('http://127.0.0.1:8765'+path); page.wait_for_timeout(600)
  page.add_script_tag(path=str(base/'browser-tools/node_modules/axe-core/axe.min.js'))
  axe=page.evaluate("async()=>await axe.run(document,{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21a','wcag21aa']}})")
  results.append({'page':name,'status':response.status,'title':page.title(),'violations':[{'id':v['id'],'impact':v['impact'],'help':v['help'],'nodes':[{'target':n['target'],'summary':n['failureSummary']} for n in v['nodes']]} for v in axe['violations']],'overflow':page.evaluate('document.documentElement.scrollWidth > innerWidth')})
  page.screenshot(path=str(base/(name+'.png')),full_page=True)
 inspect('home','/')
 inspect('login','/login')
 inspect('public-form',f"/forms/{seed['form']}/submit")
 # Guest draft is visibly offered but action returns silently.
 page.get_by_role('button',name='Save as Draft',exact=True).click(); page.wait_for_timeout(400)
 results.append({'guest_draft_button':True,'guest_draft_feedback':page.locator('body').inner_text()[-900:]})
 # Select conditional field should appear without a server action.
 page.locator(f"#field_{seed['select_field']}").select_option('Yes'); page.wait_for_timeout(700)
 results.append({'conditional_after_select':page.locator(f"#field_{seed['conditional_field']}").is_visible()})
 # Skip required step one, complete required textarea and checkbox in step two, submit.
 page.get_by_role('button',name='Next',exact=True).click(); page.wait_for_timeout(400)
 page.get_by_label('Report details',exact=False).fill('A synthetic report')
 page.get_by_label('Security',exact=True).check()
 page.get_by_role('button',name='Submit',exact=True).click(); page.wait_for_timeout(650)
 results.append({'invalid_submission_url':page.url,'first_required_field_visible':page.locator(f"#field_{seed['name_field']}").is_visible(),'visible_inline_errors':page.locator('p.text-red-600:visible').all_text_contents(),'global_error_summary':page.locator('.bg-red-50 ul').all_text_contents(),'focused_element':page.evaluate('document.activeElement.outerHTML')[:500]})
 page.screenshot(path=str(base/'hidden-validation-error.png'),full_page=True)
 page.set_viewport_size({'width':390,'height':844}); inspect('public-form-mobile',f"/forms/{seed['form']}/submit")
 page.get_by_role('button',name='Next',exact=True).click();page.wait_for_timeout(350)
 results.append({'mobile_step_2_overflow':page.evaluate('document.documentElement.scrollWidth > innerWidth'),'mobile_scroll_width':page.evaluate('document.documentElement.scrollWidth')})
 page.screenshot(path=str(base/'public-form-mobile-step2.png'),full_page=True)
 page.set_viewport_size({'width':1440,'height':1000})
 page.goto('http://127.0.0.1:8765/login')
 page.get_by_label('Email',exact=True).fill('audit-admin@nc3.lu');page.get_by_label('Password',exact=True).fill('SyntheticAuditPassword123!')
 page.get_by_role('button',name='Log in',exact=False).click();page.wait_for_timeout(700)
 results.append({'admin_login_url':page.url})
 inspect('dashboard','/dashboard')
 inspect('form-builder',f"/forms/{seed['form']}/edit")
 inspect('admin','/admin')
 results.append({'page_errors':errors})
 browser.close()
(base/'browser-results.json').write_text(json.dumps(results,indent=2))
print(json.dumps([{'page':r.get('page'),'status':r.get('status'),'violations':[(v['id'],len(v['nodes'])) for v in r.get('violations',[])]} if 'page' in r else r for r in results],indent=2))
