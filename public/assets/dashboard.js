document.querySelectorAll('time.when').forEach(function (el) {
    const date = new Date(el.dateTime);
    if (!isNaN(date.getTime())) {
        el.textContent = date.toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    }
});
