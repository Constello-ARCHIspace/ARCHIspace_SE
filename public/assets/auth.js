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
