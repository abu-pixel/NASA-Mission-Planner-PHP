# 🚀 NASA Mission Planner (Pure PHP)

A **single-file, pure PHP** orbital mission planner that simulates Earth orbits and computes **Hohmann transfer maneuvers**, renders **server-side SVG visualizations**, and generates a **mission timeline and telemetry report** — all without JavaScript, frameworks, or external libraries.

This project demonstrates strong **backend engineering**, **mathematical modeling**, and **clean system design** using PHP only.

---

## ✨ Features

* 🛰️ Orbital mechanics engine (Kepler equations, orbital period, mean motion)
* 🔁 Hohmann transfer optimizer (Δv₁, Δv₂, total Δv, time of flight)
* 📐 Server-side SVG orbit visualization (no JS, no canvas)
* 🧭 Mission timeline generation
* 📊 Telemetry snapshot (range, velocity)
* 🧾 Auto-generated mission report (copy‑paste ready)
* 📦 **Single PHP file** — easy to review, run, and deploy

---

## 🧠 Why this project matters

This project is intentionally built as a **single-file system** to show:

* Ability to design **complex logic without frameworks**
* Strong understanding of **numerical methods** (Newton–Raphson solver)
* Clean **object‑oriented PHP** architecture
* Server‑side rendering and visualization
* End‑to‑end project completion and deployment

It is suitable as a **portfolio project** for backend, simulation, or engineering‑focused roles.

---

## 🛠️ Tech Stack

* **Language:** PHP 8+
* **Rendering:** Server-side SVG
* **Dependencies:** None
* **Frontend:** Generated HTML + CSS (inline)
* **Platform:** Runs on any PHP-capable environment

---

## ▶️ How to Run Locally (Windows)

### 1️⃣ Requirements

* PHP 8+ (portable version works)

### 2️⃣ Start the PHP server

From the project folder:

```bash
php -S localhost:8000
```

If PHP is not in PATH (portable setup):

```bash
"C:\Users\user\Downloads\php.exe" -S localhost:8000
```

### 3️⃣ Open in browser

```
http://localhost:8000/NASA_Masterpiece_Mission_Planner.php
```

Click **“Run Default Demo”** to see a full mission simulation.

---

## 📂 Project Structure

```
NASA-Mission-Planner-PHP/
│
├── NASA_Masterpiece_Mission_Planner.php
└── README.md
```

---

## 📈 Example Output

* Low Earth Orbit parameters
* GEO transfer Δv calculations
* Transfer time of flight
* SVG orbit path and spacecraft position
* Mission timeline with launch and burn events

All outputs are computed **server-side**.

---

## ⚠️ Notes

* The mission timeline uses PHP timestamps; strict PHP versions may require integer casting for date formatting.
* This does **not affect** core simulation accuracy.

---

## 📌 Future Enhancements (Planned)

* Interplanetary transfers (Mars, Venus)
* Plane change Δv calculations
* Bi‑elliptic transfers
* Export mission report (PDF / JSON)
* Multi‑orbit visualization

---

## 👤 Author

**GitHub:** [abu-pixel](https://github.com/abu-pixel)

---

## 🏁 Status

✅ Project completed and operational
🚀 Ready for portfolio and demonstration use

---

> *Built to demonstrate how far pure PHP can go — even into orbital mechanics.*
