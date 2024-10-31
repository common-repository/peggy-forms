window.addEventListener("message", function(event) {
	if (event && event.origin && (event.origin === "http://view.formbuilder.local.nl" || event.origin === "https://view.peggyforms.com")) {
		var iframe = document.getElementById("peggyForms");
		iframe.style.height = event.data + "px";
	}
});