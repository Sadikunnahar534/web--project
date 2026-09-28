// ============================================
// Client-side validation (JavaScript layer)
// Runs BEFORE the form is submitted to PHP.
// Final scoring/validation always happens again on the server (PHP),
// since client-side checks can be bypassed.
// ============================================

document.addEventListener("DOMContentLoaded", function () {

    // ---- Registration form validation ----
    const registerForm = document.getElementById("registerForm");
    if (registerForm) {
        registerForm.addEventListener("submit", function (e) {
            let valid = true;
            const name = document.getElementById("name");
            const email = document.getElementById("email");
            const password = document.getElementById("password");

            clearErrors(registerForm);

            if (name.value.trim().length < 2) {
                showError(name, "Name must be at least 2 characters.");
                valid = false;
            }

            if (!validEmail(email.value)) {
                showError(email, "Enter a valid email address.");
                valid = false;
            }

            if (password.value.length < 6) {
                showError(password, "Password must be at least 6 characters.");
                valid = false;
            }

            if (!valid) e.preventDefault();
        });
    }

    // ---- Login form validation ----
    const loginForm = document.getElementById("loginForm");
    if (loginForm) {
        loginForm.addEventListener("submit", function (e) {
            let valid = true;
            const email = document.getElementById("email");
            const password = document.getElementById("password");

            clearErrors(loginForm);

            if (!validEmail(email.value)) {
                showError(email, "Enter a valid email address.");
                valid = false;
            }
            if (password.value.trim() === "") {
                showError(password, "Password is required.");
                valid = false;
            }

            if (!valid) e.preventDefault();
        });
    }

    // ---- Quiz attempt: warn if unanswered questions (MCQ/TF radios + short text) ----
    const quizForm = document.getElementById("quizForm");
    if (quizForm) {
        quizForm.addEventListener("submit", function (e) {
            const groups = quizForm.querySelectorAll(".question-block");
            let unanswered = 0;
            groups.forEach(function (group) {
                const radios = group.querySelectorAll("input[type=radio]");
                const textInput = group.querySelector("input[type=text]");
                if (radios.length > 0) {
                    const checked = group.querySelector("input[type=radio]:checked");
                    if (!checked) unanswered++;
                } else if (textInput) {
                    if (textInput.value.trim() === "") unanswered++;
                }
            });
            if (unanswered > 0) {
                if (!confirm(unanswered + " question(s) unanswered. Submit anyway?")) {
                    e.preventDefault();
                }
            }
        });
    }

    // ---- Simple countdown timer on quiz page (visual only, not enforced) ----
    const timerEl = document.getElementById("quizTimer");
    if (timerEl) {
        let seconds = parseInt(timerEl.getAttribute("data-seconds"), 10) || 900;
        setInterval(function () {
            if (seconds <= 0) return;
            seconds--;
            const m = Math.floor(seconds / 60);
            const s = seconds % 60;
            timerEl.textContent = (m < 10 ? "0" : "") + m + ":" + (s < 10 ? "0" : "") + s;
        }, 1000);
    }

    // ---- Confetti animation generator for the celebration page ----
    const confettiHost = document.getElementById("confettiHost");
    if (confettiHost) {
        const colors = ["#e67e22", "#f1c40f", "#27ae60", "#2980b9", "#8e44ad", "#e74c3c"];
        for (let i = 0; i < 60; i++) {
            const piece = document.createElement("div");
            piece.className = "confetti";
            piece.style.left = Math.random() * 100 + "%";
            piece.style.background = colors[Math.floor(Math.random() * colors.length)];
            piece.style.animationDuration = (2 + Math.random() * 2.5) + "s";
            piece.style.animationDelay = (Math.random() * 2) + "s";
            confettiHost.appendChild(piece);
        }
    }
});

function validEmail(value) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim());
}

function showError(inputEl, message) {
    const p = document.createElement("p");
    p.className = "error-text";
    p.textContent = message;
    inputEl.insertAdjacentElement("afterend", p);
    inputEl.style.borderColor = "#e74c3c";
}

function clearErrors(form) {
    form.querySelectorAll(".error-text").forEach(el => el.remove());
    form.querySelectorAll("input").forEach(el => el.style.borderColor = "#ccc");
}
