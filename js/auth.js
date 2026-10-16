let authConfig = { csrf_token: '', google_enabled: false, google_client_id: '' };

const $ = (id) => document.getElementById(id);
function showMessage(message, type='error', link=null){const box=$('authMessage');box.className='alert '+type;box.innerHTML='';box.append(document.createTextNode(message));if(link){const a=document.createElement('a');a.href=link;a.textContent=' Open link';a.className='alert-link';box.appendChild(a);}}
function hideMessage(){const box=$('authMessage');box.className='alert hidden';box.textContent='';}
function showRegister(){hideMessage();$('formTitle').textContent='Create Your Account';$('formSubtitle').textContent='Register with email or Google';$('loginForm').classList.add('hidden');$('registerForm').classList.remove('hidden');}
function showLogin(){hideMessage();$('formTitle').textContent='Welcome Back';$('formSubtitle').textContent='Sign in to continue';$('registerForm').classList.add('hidden');$('loginForm').classList.remove('hidden');}
function strongPassword(p){return p.length>=8&&/[A-Z]/.test(p)&&/[a-z]/.test(p)&&/[0-9]/.test(p)&&/[^A-Za-z0-9]/.test(p);}
function withCsrf(fd){fd.append('csrf_token',authConfig.csrf_token);return fd;}
async function jsonPost(url,fd){const r=await fetch(url,{method:'POST',body:fd,headers:{'Accept':'application/json'}});let data;try{data=await r.json();}catch{throw new Error('The server returned an invalid response.');}return data;}

async function loadConfig(){
  const r=await fetch('backend/public-config.php',{headers:{Accept:'application/json'}});
  authConfig=await r.json();
  if(!authConfig.google_enabled){
    $('googleDisabledLogin').classList.remove('hidden');
    $('googleDisabledRegister').classList.remove('hidden');
    $('googleButton').classList.add('hidden');
    $('googleRegisterButton').classList.add('hidden');
  }
  initializeGoogle();
}
async function loadTechnicians(){try{const r=await fetch('backend/technicians.php');const data=await r.json();if(data.status==='success')data.technicians.forEach(t=>{const o=document.createElement('option');o.value=t.id;o.textContent=t.full_name+(t.specialization?' — '+t.specialization:'');$('registerTechnician').appendChild(o);});}catch(e){console.error(e);}}

async function userLogin(){hideMessage();const id=$('loginIdentifier').value.trim(),pw=$('loginPassword').value;if(!id||!pw)return showMessage('Enter your email and password.');const fd=withCsrf(new FormData());fd.append('identifier',id);fd.append('password',pw);try{const d=await jsonPost('backend/user-login.php',fd);if(d.status==='success')location.href=d.redirect;else showMessage(d.message);}catch(e){showMessage(e.message);}}
async function registerUser(){hideMessage();const full=$('registerFullName').value.trim(),email=$('registerEmail').value.trim(),phone=$('registerPhone').value.trim(),pw=$('registerPassword').value,cp=$('registerConfirmPassword').value;if(!full||!email||!phone||!pw||!cp)return showMessage('Complete all required fields.');if(!strongPassword(pw))return showMessage('Password does not meet the security rules.');if(pw!==cp)return showMessage('Passwords do not match.');if(!$('registerConsent').checked)return showMessage('Please confirm the privacy/account terms.');const fd=withCsrf(new FormData());[['full_name',full],['email',email],['phone_number',phone],['password',pw],['confirm_password',cp],['date_of_birth',$('registerDob').value],['preferred_technician_id',$('registerTechnician').value],['nail_preferences',$('registerNailPreferences').value.trim()],['allergy_notes',$('registerAllergies').value.trim()]].forEach(([k,v])=>fd.append(k,v));try{const d=await jsonPost('backend/register.php',fd);if(d.status==='success')showMessage(d.message,'success',d.verification_url||null);else showMessage(d.message);}catch(e){showMessage(e.message);}}

function initializeGoogle(){
  if(!authConfig.google_enabled)return;
  if(typeof google==='undefined'||!google.accounts){setTimeout(initializeGoogle,300);return;}
  google.accounts.id.initialize({client_id:authConfig.google_client_id,callback:handleGoogle,auto_select:false,cancel_on_tap_outside:true});
  [['googleButton','continue_with'],['googleRegisterButton','signup_with']].forEach(([id,text])=>{
    const el=$(id); if(!el)return; el.innerHTML='';
    google.accounts.id.renderButton(el,{theme:'outline',size:'large',text,shape:'rectangular',logo_alignment:'left',width:360});
  });
}
async function handleGoogle(response){
  if(!response||!response.credential)return showMessage('Google did not return a sign-in credential.');
  const fd=withCsrf(new FormData());fd.append('credential',response.credential);
  try{const d=await jsonPost('backend/google-auth.php',fd);if(d.status==='success')location.href=d.redirect;else showMessage(d.message);}catch(e){showMessage(e.message);}
}

document.addEventListener('DOMContentLoaded',()=>{
  $('showRegisterBtn').addEventListener('click',showRegister);
  $('showLoginBtn').addEventListener('click',showLogin);
  $('loginButton').addEventListener('click',userLogin);
  $('registerButton').addEventListener('click',registerUser);
  $('showLoginPassword').addEventListener('change',e=>$('loginPassword').type=e.target.checked?'text':'password');
  const params=new URLSearchParams(location.search);if(params.get('verified')==='1')showMessage('Email verified. You can sign in now.','success');
  if(location.hash==='#register')showRegister();
  Promise.all([loadConfig(),loadTechnicians()]).catch(()=>showMessage('Some sign-in options could not be loaded.'));
});
