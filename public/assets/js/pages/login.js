async function handleLoginSubmit(e) {
  e.preventDefault();
  const alertBox = document.getElementById("login-alert");
  if (alertBox) alertBox.style.display = "none";

  const usernameInput = document.getElementById("username");
  const passwordInput = document.getElementById("password");

  const username = usernameInput ? usernameInput.value.trim() : "";
  const password = passwordInput ? passwordInput.value.trim() : "";

  if (!username || !password) {
    if (alertBox) {
      alertBox.textContent = "Ingrese usuario y contraseña";
      alertBox.style.display = "block";
    }
    return;
  }

  try {
    const res = await apiFetch("/api/auth/login", {
      method: "POST",
      body: JSON.stringify({ username, password }),
    });

    if (res.ok && res.data.status === "success") {
      localStorage.setItem("cs_user", JSON.stringify(res.data.data));
      window.location.href = "/";
    } else {
      if (alertBox) {
        alertBox.textContent =
          res.data.message || "Usuario o contraseña incorrectos";
        alertBox.style.display = "block";
      }
    }
  } catch (err) {
    if (alertBox) {
      alertBox.textContent = "Error de conexión con el servidor";
      alertBox.style.display = "block";
    }
  }
}
