const menuToggle = document.getElementById("menuToggle");
const menu = document.getElementById("menu");

if (menuToggle && menu) {
    menuToggle.addEventListener("click", () => {
        menu.classList.toggle("open");
    });
    menu.querySelectorAll("a").forEach((item) => {
        item.addEventListener("click", () => menu.classList.remove("open"));
    });
}

document.querySelectorAll(".faq-q").forEach((button) => {
    button.addEventListener("click", () => {
        const card = button.closest(".faq");
        if (!card) return;
        const active = card.classList.contains("active");
        document.querySelectorAll(".faq").forEach((el) => el.classList.remove("active"));
        if (!active) card.classList.add("active");
    });
});

const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
        if (entry.isIntersecting) {
            entry.target.classList.add("visible");
            observer.unobserve(entry.target);
        }
    });
}, { threshold: 0.15 });

document.querySelectorAll(".reveal").forEach((el) => observer.observe(el));

const swapButton = document.getElementById("swapButton");
const toState = document.getElementById("toState");
if (swapButton && toState) {
    swapButton.addEventListener("click", () => {
        toState.classList.toggle("active");
    });
}

document.querySelectorAll(".tilt-card").forEach((card) => {
    card.addEventListener("mousemove", (event) => {
        const rect = card.getBoundingClientRect();
        const x = event.clientX - rect.left;
        const y = event.clientY - rect.top;
        const rotateX = ((y / rect.height) - 0.5) * -6;
        const rotateY = ((x / rect.width) - 0.5) * 6;
        card.style.transform = `perspective(700px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-3px)`;
    });
    card.addEventListener("mouseleave", () => {
        card.style.transform = "";
    });
});
