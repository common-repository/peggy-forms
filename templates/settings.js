function peggyFormsAssignDomain(formKey, assign, callback) {
	if (typeof callback !== "function") { callback = function() {}; }
	// callback();return;

	jQuery.post(
		ajaxurl,
		{
			action: "fb_updateDomainAssignment",
			formKey: formKey,
			assign: assign
		},
		callback
	);
}

jQuery(document).ready(function() {
	jQuery(".assign-domain input").on("change", function() {
		peggyFormsAssignDomain(jQuery(this).data("form-key"), jQuery(this).is(":checked") ? "add" : "remove");
	});

	jQuery(".remove-domain").on("click", function() {
		var dom = this;

		peggyFormsAssignDomain(jQuery(dom).data("form-key"), "remove", function() {
			jQuery(dom).closest("tr").fadeOut(function() { jQuery(dom).remove(); });
		});
	});
});