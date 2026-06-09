function showRegister() {
  document.getElementById("formTitle").innerText = "User Register";
  document.getElementById("registerFields").classList.remove("hidden");
}

function userLogin() {
  let username = document.getElementById("username").value;
  let password = document.getElementById("password").value;

  if (username === "" || password === "") {
    alert("Please enter your username and password.");
    return;
  }

  alert("Login successful!");
  window.location.href = "user-dashboard.html";
}

function registerUser() {
  let username = document.getElementById("username").value;
  let password = document.getElementById("password").value;
  let email = document.getElementById("email").value;
  let phone = document.getElementById("phone").value;

  if (username === "" || password === "" || email === "" || phone === "") {
    alert("Please complete all registration fields.");
    return;
  }

  alert("Registration successful! You can now login.");
  document.getElementById("registerFields").classList.add("hidden");
  document.getElementById("formTitle").innerText = "User Login";
}

function adminLogin() {
  let adminUsername = document.getElementById("adminUsername").value;
  let adminPassword = document.getElementById("adminPassword").value;

  if (adminUsername === "admin" && adminPassword === "admin123") {
    alert("Admin login successful!");
    window.location.href = "admin-dashboard.html";
  } else {
    alert("Invalid admin login. Try admin / admin123");
  }
}

function submitBooking(event) {
  event.preventDefault();

  let name = document.getElementById("fullName").value;
  let service = document.getElementById("serviceType").value;
  let date = document.getElementById("bookingDate").value;
  let time = document.getElementById("bookingTime").value;
  let payment = document.getElementById("paymentType").value;

  let booking = {
    name: name,
    service: service,
    date: date,
    time: time,
    payment: payment,
    status: "Pending"
  };

  localStorage.setItem("latestBooking", JSON.stringify(booking));

  alert("Booking submitted successfully!");
  window.location.href = "booking-history.html";
}

function changeNailColor() {
  let color = document.getElementById("nailColor").value;
  document.getElementById("nailPreview").style.background = color;
}