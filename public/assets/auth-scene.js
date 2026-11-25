function initHouseScene() {
    const host = document.querySelector(".house-scene");
    const canvas = host.querySelector("canvas");
    const stageSteps = [...host.querySelectorAll(".stage-step")];
    const stageLabel = host.querySelector(".stage-label");
    const toggle = host.querySelector(".scene-toggle");
    const motion = matchMedia("(prefers-reduced-motion: reduce)");
    if (!window.THREE || !window.gsap) return;
    const STAGES = ["Blueprint", "Foundation", "Structure", "Envelope", "Finishing"];
    function setStage(i) {
        stageSteps.forEach((el, k) => {
            el.classList.toggle("active", k === i);
            el.classList.toggle("done", k < i);
        });
        stageLabel.querySelector("b").textContent = String(i + 1).padStart(2, "0");
        stageLabel.querySelector("span").textContent = STAGES[i];
    }

    /* ---------- Renderer ---------- */

    let renderer;
    try {
        renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true, powerPreference: "low-power" });
    } catch {

        return;
    }

    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.05;
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = THREE.PCFSoftShadowMap;

    const scene = new THREE.Scene();
    scene.fog = new THREE.Fog(0x081526, 20, 38);

    const camera = new THREE.PerspectiveCamera(30, 1, 0.1, 100);
    const target = new THREE.Vector3(0.4, 1.9, 0);

    /* ---------- Colors ---------- */

    const TEAL = 0x2dd4bf;
    const GOLD = 0xe0b45a;

    /* ---------- Lights ---------- */

    const hemi = new THREE.HemisphereLight(0xcfeeff, 0x0a1628, 1.0);
    const sun = new THREE.DirectionalLight(0xfff1d6, 2.2);
    sun.position.set(7, 11, 6);
    sun.castShadow = true;
    sun.shadow.mapSize.set(1024, 1024);
    Object.assign(sun.shadow.camera, { left: -9, right: 9, top: 9, bottom: -9, near: 1, far: 32 });
    sun.shadow.bias = -0.0006;
    const rim = new THREE.DirectionalLight(TEAL, 0.9);
    rim.position.set(-7, 5, -6);
    const warm = new THREE.PointLight(GOLD, 0, 9, 1.6);
    warm.position.set(0.4, 2.2, 2.6);
    scene.add(hemi, sun, rim, warm);

    /* ---------- Ground + blueprint grid ---------- */

    const ground = new THREE.Mesh(
        new THREE.CircleGeometry(10, 64),
        new THREE.MeshStandardMaterial({ color: 0x0d1f36, roughness: 1 })
    );
    ground.rotation.x = -Math.PI / 2;
    ground.receiveShadow = true;
    scene.add(ground);

    const grid = new THREE.GridHelper(18, 36, 0x2dd4bf, 0x1b4660);
    grid.material.transparent = true;
    grid.material.opacity = 0.28;
    grid.position.y = 0.004;
    scene.add(grid);

    /* ---------- Floor plan (blueprint lines) ---------- */

    const planPts = [];
    const L = (x0, z0, x1, z1) => planPts.push(x0, 0, z0, x1, 0, z1);
    const R = (x0, z0, x1, z1) => { L(x0, z0, x1, z0); L(x1, z0, x1, z1); L(x1, z1, x0, z1); L(x0, z1, x0, z0); };

    R(-3, -2, 3, 2);                 // ground floor footprint
    L(-0.6, -2, -0.6, 0.4);          // interior walls
    L(-3, 0.4, -1.6, 0.4);
    L(1.2, -2, 1.2, -0.4);
    L(1.2, -0.4, 3, -0.4);
    R(-0.6, -2.2, 3.8, 1.6);         // upper floor (cantilever) outline
    L(-3, 2.7, 3, 2.7);              // dimension line + ticks
    L(-3, 2.55, -3, 2.85); L(3, 2.55, 3, 2.85); L(0, 2.6, 0, 2.8);
    L(-3.7, -2, -3.7, 2);
    L(-3.85, -2, -3.55, -2); L(-3.85, 2, -3.55, 2);
    // door swing
    const dx = 1.42, dz = 2, dr = 0.95;
    for (let i = 0; i < 10; i++) {
        const a0 = Math.PI - (i / 10) * (Math.PI / 2);
        const a1 = Math.PI - ((i + 1) / 10) * (Math.PI / 2);
        L(dx + 0.95 + Math.cos(a0) * dr, dz - Math.sin(a0) * dr, dx + 0.95 + Math.cos(a1) * dr, dz - Math.sin(a1) * dr);
    }
    // stair treads
    for (let i = 0; i < 7; i++) L(-2.9, -1.9 + i * 0.32, -1.7, -1.9 + i * 0.32);

    const planGeo = new THREE.BufferGeometry();
    planGeo.setAttribute("position", new THREE.Float32BufferAttribute(planPts, 3));
    const planMat = new THREE.LineBasicMaterial({ color: TEAL, transparent: true, opacity: 0 });
    const plan = new THREE.LineSegments(planGeo, planMat);
    plan.position.y = 0.02;
    scene.add(plan);
    const planCount = planPts.length / 3;

    /* ---------- House parts ---------- */

    const house = new THREE.Group();
    scene.add(house);
    const parts = [];

    function block(w, h, d, x, y, z, opts = {}) {
        const geo = new THREE.BoxGeometry(w, h, d);
        geo.translate(0, h / 2, 0);            // pivot at base so it "rises"
        const mat = opts.material || new THREE.MeshStandardMaterial({
            color: opts.color ?? 0xeae6dc,
            roughness: opts.rough ?? 0.78,
            metalness: opts.metal ?? 0
        });
        mat.transparent = true;
        mat.opacity = 0;
        const mesh = new THREE.Mesh(geo, mat);
        mesh.castShadow = opts.shadow !== false;
        mesh.receiveShadow = true;
        const edges = new THREE.LineSegments(
            new THREE.EdgesGeometry(geo),
            new THREE.LineBasicMaterial({ color: opts.edge ?? TEAL, transparent: true, opacity: 0 })
        );
        const g = new THREE.Group();
        g.add(mesh, edges);
        g.position.set(x, y, z);
        g.scale.y = 0.001;
        house.add(g);
        const p = { g, mesh, mat, emat: edges.material, to: opts.opacity ?? 1, baseY: y };
        parts.push(p);
        return p;
    }

    const glassMat = () => new THREE.MeshStandardMaterial({
        color: 0x0f2b3d, roughness: 0.08, metalness: 0.35,
        emissive: new THREE.Color(0xf5c66b), emissiveIntensity: 0
    });

    // heights: foundation 0–0.2, ground 0.2–2.4, slab 2.4–2.54, upper 2.54–4.54, roof 4.54–4.7
    const foundation = block(6.4, 0.2, 4.4, 0, 0, 0, { color: 0x8b96a3, rough: 0.95 });
    const path = block(1.1, 0.04, 1.6, 2.0, 0, 3.0, { color: 0x9aa6b2, rough: 0.95, shadow: false });

    const groundWalls = block(6, 2.2, 4, 0, 0.2, 0, { color: 0xeee9df });
    const slab = block(6.3, 0.14, 4.3, 0, 2.4, 0, { color: 0xd9d3c7 });
    const upper = block(4.4, 2.0, 3.8, 1.6, 2.54, -0.3, { color: 0x243142, rough: 0.6, metal: 0.1 });
    const roof = block(4.9, 0.16, 4.3, 1.6, 4.54, -0.3, { color: 0x1a2533, rough: 0.55 });

    const gFront = block(3.0, 1.6, 0.06, -1.3, 0.5, 2.02, { material: glassMat(), opacity: 0.92 });
    const gSide = block(0.06, 1.4, 2.2, -3.02, 0.6, -0.4, { material: glassMat(), opacity: 0.92 });
    const door = block(0.95, 1.95, 0.07, 2.0, 0.2, 2.03, { color: 0x8a5a33, rough: 0.6 });
    const uFront = block(3.8, 1.15, 0.06, 1.6, 2.95, 1.62, { material: glassMat(), opacity: 0.92 });
    const uSide = block(0.06, 1.15, 2.6, 3.82, 2.95, -0.4, { material: glassMat(), opacity: 0.92 });

    const trimRoof = block(4.9, 0.09, 0.07, 1.6, 4.62, 1.86, { color: GOLD, metal: 0.7, rough: 0.3, edge: GOLD });
    const trimSlab = block(6.3, 0.06, 0.06, 0, 2.47, 2.17, { color: GOLD, metal: 0.7, rough: 0.3, edge: GOLD });

    // railing on the terrace (over the ground floor, left of the upper volume)
    const rail = block(0.04, 0.55, 3.9, -3.08, 2.54, 0, {
        material: new THREE.MeshStandardMaterial({ color: 0x9fe8de, roughness: 0.1, metalness: 0.2 }),
        opacity: 0.35, shadow: false
    });

    // trees
    function tree(x, z, s) {
        const trunk = block(0.14 * s, 0.7 * s, 0.14 * s, x, 0, z, { color: 0x5b4330 });
        const crownGeo = new THREE.IcosahedronGeometry(0.62 * s, 0);
        const crownMat = new THREE.MeshStandardMaterial({ color: 0x1f8a74, roughness: 0.8, flatShading: true, transparent: true, opacity: 0 });
        const crown = new THREE.Mesh(crownGeo, crownMat);
        crown.castShadow = true;
        crown.position.set(x, 0.7 * s + 0.45 * s, z);
        crown.scale.setScalar(0.001);
        house.add(crown);
        return { trunk, crown, crownMat };
    }
    const treeA = tree(-4.3, 1.6, 1.1);
    const treeB = tree(5.0, -2.2, 1.3);
    const treeC = tree(-4.0, -2.4, 0.85);

    const allGlass = [gFront, gSide, uFront, uSide];

    /* ---------- Build timeline ---------- */

    let tl = null;

    function buildTimeline() {
        tl = gsap.timeline({ repeat: -1, repeatDelay: 0.3, defaults: { ease: "power2.out" } });
        const draw = { n: 0 };

        const rise = (p, at, dur = 0.9) => {
            tl.fromTo(p.g.scale, { y: 0.001 }, { y: 1, duration: dur, ease: "power3.out" }, at)
              .fromTo(p.emat, { opacity: 0 }, { opacity: 1, duration: 0.25 }, "<")
              .fromTo(p.mat, { opacity: 0 }, { opacity: p.to, duration: 0.6 }, "<+" + (dur * 0.55))
              .to(p.emat, { opacity: 0.15, duration: 0.6 }, "<");
        };

        // 01 — Blueprint
        tl.call(setStage, [0], 0)
          .fromTo(planMat, { opacity: 0 }, { opacity: 0.95, duration: 0.4 }, 0)
          .fromTo(draw, { n: 0 }, {
              n: planCount, duration: 2.4, ease: "power1.inOut",
              onUpdate: () => plan.geometry.setDrawRange(0, Math.floor(draw.n / 2) * 2)
          }, 0);

        // 02 — Foundation
        tl.call(setStage, [1], 2.5);
        rise(foundation, 2.5, 0.7);
        rise(path, 2.9, 0.5);

        // 03 — Structure
        tl.call(setStage, [2], 3.6);
        rise(groundWalls, 3.6, 1.1);
        rise(slab, 4.6, 0.5);
        rise(upper, 5.0, 1.1);

        // 04 — Envelope (glass, door, roof drops into place)
        tl.call(setStage, [3], 6.3);
        rise(gFront, 6.3, 0.6);
        rise(gSide, 6.45, 0.6);
        rise(door, 6.6, 0.6);
        rise(uFront, 6.75, 0.6);
        rise(uSide, 6.9, 0.6);
        tl.fromTo(roof.g.position, { y: roof.baseY + 1.8 }, { y: roof.baseY, duration: 0.9, ease: "back.out(1.6)" }, 7.1)
          .fromTo(roof.g.scale, { y: 0.001 }, { y: 1, duration: 0.25 }, 7.1)
          .fromTo(roof.emat, { opacity: 0 }, { opacity: 1, duration: 0.25 }, 7.1)
          .fromTo(roof.mat, { opacity: 0 }, { opacity: 1, duration: 0.5 }, 7.3)
          .to(roof.emat, { opacity: 0.15, duration: 0.5 }, 7.6);
        rise(rail, 7.6, 0.5);

        // 05 — Finishing (trim, landscaping, lights on)
        tl.call(setStage, [4], 8.2);
        rise(trimSlab, 8.2, 0.5);
        rise(trimRoof, 8.3, 0.5);
        [treeA, treeB, treeC].forEach((t, i) => {
            const at = 8.4 + i * 0.15;
            rise(t.trunk, at, 0.4);
            tl.fromTo(t.crown.scale, { x: 0.001, y: 0.001, z: 0.001 }, { x: 1, y: 1, z: 1, duration: 0.7, ease: "back.out(2)" }, at + 0.25)
              .fromTo(t.crownMat, { opacity: 0 }, { opacity: 1, duration: 0.4 }, at + 0.25);
        });
        tl.fromTo(planMat, { opacity: 0.95 }, { opacity: 0.22, duration: 1 }, 8.6)
          .fromTo(sun, { intensity: 2.2 }, { intensity: 1.2, duration: 1.4 }, 8.8)
          .fromTo(hemi, { intensity: 1.0 }, { intensity: 0.6, duration: 1.4 }, 8.8)
          .fromTo(warm, { intensity: 0 }, { intensity: 6, duration: 1.2 }, 9.0);
        allGlass.forEach((p, i) => tl.fromTo(p.mat, { emissiveIntensity: 0 }, { emissiveIntensity: 0.95, duration: 0.6 }, 9.0 + i * 0.12));

        tl.addLabel("complete", 10.2);

        // hold the finished home, then dissolve and loop
        const fadeAt = 14.2;
        parts.forEach(p => tl.to([p.mat, p.emat], { opacity: 0, duration: 0.8, ease: "power1.in" }, fadeAt));
        [treeA, treeB, treeC].forEach(t => tl.to(t.crownMat, { opacity: 0, duration: 0.8 }, fadeAt));
        tl.to(planMat, { opacity: 0, duration: 0.8 }, fadeAt)
          .to(sun, { intensity: 2.2, duration: 0.8 }, fadeAt)
          .to(hemi, { intensity: 1.0, duration: 0.8 }, fadeAt)
          .to(warm, { intensity: 0, duration: 0.8 }, fadeAt);
        allGlass.forEach(p => tl.to(p.mat, { emissiveIntensity: 0, duration: 0.8 }, fadeAt));
    }

    let userPaused = false;
    let visible = false;
    let contextLost = false;
    let angle = 0.65;
    const pointer = { x: 0, y: 0, tx: 0, ty: 0 };
    const panel = host.closest(".story");

    function render(time, deltaMs = 0) {
        angle += Math.min(deltaMs / 1000, 0.05) * 0.11;
        pointer.x += (pointer.tx - pointer.x) * 0.05;
        pointer.y += (pointer.ty - pointer.y) * 0.05;
        const radius = camera.aspect < 1.1 ? 21 : 17.5;
        const orbit = angle + pointer.x * 0.18;
        camera.position.set(
            target.x + Math.sin(orbit) * radius,
            7.2 - pointer.y * 1.2,
            target.z + Math.cos(orbit) * radius
        );
        camera.lookAt(target);
        renderer.render(scene, camera);
    }

    function syncPlayback() {
        const playing = visible && !document.hidden && !contextLost && !userPaused && !motion.matches;
        tl.paused(!playing);
        gsap.ticker.remove(render);
        if (playing) gsap.ticker.add(render);
        toggle.hidden = motion.matches || contextLost;
        toggle.textContent = userPaused ? "Resume" : "Pause";
        toggle.setAttribute("aria-label", userPaused ? "Resume house animation" : "Pause house animation");
    }

    function syncPreference() {
        if (motion.matches) {
            tl.pause(10.3);
            setStage(4);
            angle = 0.65;
            pointer.x = pointer.y = pointer.tx = pointer.ty = 0;
        } else {
            tl.pause(0);
            setStage(0);
        }
        if (!contextLost) render();
        syncPlayback();
    }

    panel.addEventListener("pointermove", event => {
        const rect = panel.getBoundingClientRect();
        pointer.tx = 2 * (event.clientX - rect.left) / rect.width - 1;
        pointer.ty = 2 * (event.clientY - rect.top) / rect.height - 1;
    });
    panel.addEventListener("pointerleave", () => { pointer.tx = pointer.ty = 0; });
    toggle.addEventListener("click", () => {
        userPaused = !userPaused;
        syncPlayback();
    });
    new ResizeObserver(() => {
        const { clientWidth: width, clientHeight: height } = host;
        if (!width || !height || contextLost) return;
        renderer.setSize(width, height, false);
        camera.aspect = width / height;
        camera.updateProjectionMatrix();
        render();
    }).observe(host);
    new IntersectionObserver(([entry]) => {
        visible = entry.isIntersecting;
        syncPlayback();
    }).observe(host);
    document.addEventListener("visibilitychange", syncPlayback);
    motion.addEventListener("change", syncPreference);
    canvas.addEventListener("webglcontextlost", () => {
        contextLost = true;
        host.classList.remove("scene-ready");
        setStage(4);
        syncPlayback();
    });
    canvas.addEventListener("webglcontextrestored", () => {
        contextLost = false;
        syncPreference();
        host.classList.add("scene-ready");
    });

    buildTimeline();
    syncPreference();
    host.classList.add("scene-ready");
}

initHouseScene();

