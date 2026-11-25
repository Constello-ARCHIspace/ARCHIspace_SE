<aside class="story" aria-label="About ARCHIspace">
    <a class="brand" href="auth.php" aria-label="ARCHIspace home">
        <img src="assets/figma/login-imgArchispaceMark.svg" width="32" height="32" alt="">
        ARCHIspace
    </a>
    <div class="story-heading">
        <h2>Dream it. Design it.<br><span>Build it.</span></h2>
    </div>
    <figure class="model house-scene" aria-label="A floor plan becoming a two-storey house">
        <img class="scene-poster" src="assets/auth-house.webp" width="909" height="490" alt="A completed two-storey house on a drafting grid.">
        <canvas aria-hidden="true"></canvas>
        <figcaption class="scene-controls">
            <span class="stage-steps" aria-hidden="true"><?php for ($step = 1; $step <= 5; $step++): ?><i class="stage-step <?= $step === 5 ? 'active' : 'done' ?>"></i><?php endfor; ?></span>
            <span class="stage-label"><b>05</b> <span>Finishing</span></span>
            <button type="button" class="scene-toggle" hidden>Pause</button>
        </figcaption>
    </figure>
    <p class="story-description">Start with a blueprint, explore your layout in 3D, and share your design for review.</p>
</aside>
