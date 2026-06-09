function showRegister() {
  document.getElementById("formTitle").innerText = "User Register";
  document.getElementById("loginForm").classList.add("hidden");
  document.getElementById("registerForm").classList.remove("hidden");
}

function showLogin() {
  document.getElementById("formTitle").innerText = "User Login";
  document.getElementById("registerForm").classList.add("hidden");
  document.getElementById("loginForm").classList.remove("hidden");
}

function isStrongPassword(password) {
  const hasMinimumLength = password.length >= 8;
  const hasUppercase = /[A-Z]/.test(password);
  const hasLowercase = /[a-z]/.test(password);
  const hasNumber = /[0-9]/.test(password);
  const hasSpecialCharacter = /[^A-Za-z0-9]/.test(password);

  return (
    hasMinimumLength &&
    hasUppercase &&
    hasLowercase &&
    hasNumber &&
    hasSpecialCharacter
  );
}

async function registerUser() {
  const fullName = document.getElementById("registerFullName").value.trim();
  const email = document.getElementById("registerEmail").value.trim();
  const password = document.getElementById("registerPassword").value;

  if (fullName === "" || email === "" || password === "") {
    alert("Please complete all fields.");
    return;
  }

  if (!isStrongPassword(password)) {
    alert("Password must have at least 8 characters, 1 uppercase letter, 1 lowercase letter, 1 number, and 1 special character.");
    return;
  }

  const formData = new FormData();
  formData.append("full_name", fullName);
  formData.append("email", email);
  formData.append("password", password);

  const response = await fetch("backend/register.php", {
    method: "POST",
    body: formData
  });

  const result = await response.json();

  alert(result.message);

  if (result.status === "success") {
    showLogin();
  }
}

async function userLogin() {
  const email = document.getElementById("loginEmail").value.trim();
  const password = document.getElementById("loginPassword").value;

  if (email === "" || password === "") {
    alert("Please enter your email and password.");
    return;
  }

  const formData = new FormData();
  formData.append("email", email);
  formData.append("password", password);

  const response = await fetch("backend/user-login.php", {
    method: "POST",
    body: formData
  });

  const result = await response.json();

  alert(result.message);

  if (result.status === "success") {
    window.location.href = result.redirect;
  }
}

async function adminLogin() {
  const email = document.getElementById("adminEmail").value.trim();
  const password = document.getElementById("adminPassword").value;

  if (email === "" || password === "") {
    alert("Please enter admin email and password.");
    return;
  }

  const formData = new FormData();
  formData.append("email", email);
  formData.append("password", password);

  const response = await fetch("backend/admin-login.php", {
    method: "POST",
    body: formData
  });

  const result = await response.json();

  alert(result.message);

  if (result.status === "success") {
    window.location.href = result.redirect;
  }
}