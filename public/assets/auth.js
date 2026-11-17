document.querySelectorAll('[data-toggle]').forEach(button => {
  const original = button.textContent;
  button.addEventListener('click', () => {
    const input = document.getElementById(button.dataset.toggle);
    const shown = input.type === 'password';
    input.type = shown ? 'text' : 'password';
    button.textContent = shown ? (original.includes('password') ? 'Hide password' : 'Hide') : original;
    button.setAttribute('aria-label', `${shown ? 'Hide' : 'Show'} ${input.id === 'confirmation' ? 'confirmation ' : ''}password`);
    button.setAttribute('aria-pressed', String(shown));
  });
});
document.querySelector('form')?.addEventListener('submit', event => {
  const button = event.currentTarget.querySelector('[type="submit"]');
  button.disabled = true; button.textContent = 'Please wait…';
});
const help = document.getElementById('helpDialog');
document.getElementById('accountHelp')?.addEventListener('click', () => help.showModal());
document.getElementById('closeHelp')?.addEventListener('click', () => help.close());

// Registration: live length counter and "passwords match" indicator.
(() => {
  const pw = document.getElementById('password'), confirmation = document.getElementById('confirmation');
  const count = document.getElementById('password-count'), match = document.getElementById('password-match');
  if (!pw || !confirmation || !count || !match) return;
  const bytes = s => new TextEncoder().encode(s).length;
  const update = () => {
    const n = [...pw.value].length, b = bytes(pw.value);
    count.textContent = b > 72 ? `${n} characters: too long (over 72 bytes)` : n >= 15 ? `${n} characters ✓` : `${n} of 15 characters`;
    count.className = 'pw-status ' + (n >= 15 && b <= 72 ? 'ok' : 'bad');
    if (!confirmation.value) { match.textContent = ''; match.className = 'hint pw-status'; return; }
    const same = pw.value === confirmation.value;
    match.textContent = same ? 'Passwords match ✓' : 'Passwords do not match yet';
    match.className = 'hint pw-status ' + (same ? 'ok' : 'bad');
  };
  pw.addEventListener('input', update); confirmation.addEventListener('input', update); update();
})();
