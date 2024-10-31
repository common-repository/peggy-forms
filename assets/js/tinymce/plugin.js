(function() {
	tinymce.PluginManager.add("peggyforms", function (editor, url) {
		var plugin = {
			formsDict: null,
			fieldsDict: {},

			getMetadata: function() {
				return {
					name: "Peggy Forms",
					longname: "Pick a Peggy Forms form",
					version: 1.0
				};
			},

			getForms: function() {
				return new Promise(function(resolve, reject) {
					if (!plugin.formsCache) {
						editor.setProgressState(1);

						jQuery.post(ajaxurl, { action: "fb_get_forms" }, function(result) {
							var forms = result.forms;
							formsDict = {};

							for(var x = 0; x < forms.length; x++) { formsDict[forms[x].Key] = forms[x]; }
							var comboboxValues = [{text: "Select your form", value: null}];

							for (var i = 0; i < forms.length; i++) {
								var form = forms[i];
								var formName = form.Name;

								if (!form.isEnabledForThisDomain) {
									formName += " (not enabled for this domain)";
								}

								comboboxValues.push({text: formName, value: form.Key });
							}

							plugin.formsCache = comboboxValues;

							resolve(comboboxValues);
						}, "JSON").always(function() { editor.setProgressState(0); });
					} else {
						resolve(plugin.formsCache);
					}
				});
			},
			getFields: function(formKey) {
				return new Promise(function(resolve, reject) {
					if (!plugin.fieldsDict[formKey]) {
						editor.setProgressState(1);
						jQuery.post(ajaxurl, { action: "fb_get_fields", formKey: formKey }, function(result) {
							var fields = result.formElements;
							var comboboxValues = [];

							for (var i = 0; i < fields.length; i++) {
								var field = fields[i];
								var fieldName = field.Label || field.Name;

								comboboxValues.push({text: fieldName, value: field.Name });
							}

							plugin.fieldsDict[formKey] = comboboxValues;

							resolve(comboboxValues);
						}, "JSON").always(function() { editor.setProgressState(0); });
					} else {
						resolve(plugin.fieldsDict[formKey]);
					}
				});
			},

			selectForm: function() {
				if (editor.getContent().match(/\[peggyforms key=["'][^"']+["']\]/)) {
					alert("You only can use 1 peggy form on a page.");
					return false;
				}

				plugin.getForms().then(function(forms) {
					editor.windowManager.open({
						title: "Select form",
						width: 550,
						height: 90,
						body: [
							{ type: "listbox", name: "form", size: 40, autofocus:true, text: "Select your form", values: forms }
						],
						onsubmit: function(e) {
							var form = formsDict[e.data.form];

							if (!!form && !form.isEnabledForThisDomain) {
								alert("This form is not enabled for this domain. Remember to enable this form in the Peggy Forms settings in wordpress.");
							}


							editor.insertContent("[peggyforms key=\"" + e.data.form + "\"]");
						}
					});
				});
			},

			fillFields: function() {
				var formKey = this.value();
				// console.log(this);
				plugin.selectFieldStep2(plugin.formsCache, formKey);

			},
			selectField: function() {
				var listbox;

				plugin.getForms().then(function(forms) {
					editor.windowManager.open({
						title: "Form",
						width: 780,
						height: 140,
						body: [
							{ label: "Form", type: "listbox", name: "form", size: 40, autofocus: true, text: "Select your form", values: forms, onselect: plugin.fillFields }
						],
					});
				});
			},
			selectFieldStep2: function(forms, formKey) {
				plugin.getFields(formKey).then(function(fields) {
					editor.windowManager.getWindows()[0].close();

					editor.windowManager.open({
						title: "Field name",
						width: 780,
						height: 140,
						body: [
							{ label: "Form", type: "listbox", name: "form", size: 40, autofocus: true, text: "Select your form", values: forms, onselect: plugin.fillFields,
								onPostRender: function() {
									this.value(formKey);
								}
							},
							{ label: "Field", type: "listbox", name: "field", size: 40, text: "Select your field", values: fields }
						],
						onsubmit: function(e) {
							editor.insertContent("[peggyvalue field=\"" + e.data.field + "\"]");
						},
					});
				});
			}
		};

		editor.addCommand("peggyFormsPickform", plugin.selectForm);

		return plugin;
	});
})();