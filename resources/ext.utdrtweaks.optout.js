let matomoLoaded = false;
const form = document.getElementById('mw-content-text').querySelector('form');
let messageBox = document.createElement('div');
form.prepend(messageBox);
const checkbox = document.getElementById('wpOptout');
form.addEventListener('submit', event => {
	event.preventDefault();
	if (!matomoLoaded) {
		checkbox.checkbox = false;
		messageBox.remove();
		messageBox = mw.util.messageBox(mw.msg('utdr-optout-error'), 'error');
		form.prepend(messageBox);
		return;
	}
	if (checkbox.checked) {
		window._paq.push(['optUserOut']);
	} else {
		window._paq.push(['forgetUserOptOut']);
	}
	messageBox.remove();
	messageBox = mw.util.messageBox(mw.msg('utdr-optout-success'), 'success');
	form.prepend(messageBox);
});
window._paq = window._paq || [];
window._paq.push([function() {
	matomoLoaded = true;
	if (this.isUserOptedOut()) {
		checkbox.checked = true;
	}
}]);
