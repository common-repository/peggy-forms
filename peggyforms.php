<?php
	if ( ! defined( 'ABSPATH' ) ) exit;

	/*
		Plugin Name: Peggy Suite
		Description: Peggy Suite
		Version: 1.40.13
	*/

	class PeggyForms {
		const EmbedScriptVersion = "1.3";

		// Api Key
		public $key;

		// Choose Peggy Suite App
		public $app;

		// Urls and names
		public $environments;

		public $forms;

		public $host;

		public $frontEndLoaded = false;

		public $accessToken;

		public function getApp() { return isset($this->environments[$this->app]) ? $this->environments[$this->app] : null; }
		private function getAppProp($prop, $default = null) { $app = $this->getApp(); if (!$app) return $default; return $app->$prop; }
		public function getAppName($default = null) { return $this->getAppProp("name", $default); }
		public function getAppBaseUrl() { return $this->getAppProp("baseUrl"); }
		public function getAppBaseUrlViewer() { return $this->getAppProp("baseUrlViewer"); }

		private function giveHost($host_with_subdomain) {
		    $array = explode(".", $host_with_subdomain);

		    return (array_key_exists(count($array) - 2, $array) ? $array[count($array) - 2] : "").".".$array[count($array) - 1];
		}

		public function __construct() {
						$this->environments = [				"peggyforms" => (object)[
								"baseUrl" => "https://www.peggyforms.com",
								"baseUrlViewer" => "https://view.peggyforms.com",
								"name" => "Peggy Forms",
								"slug" => "peggyforms"
							],
										"peggypay" => (object)[
								"baseUrl" => "https://www.peggypay.com",
								"baseUrlViewer" => "https://view.peggypay.com",
								"name" => "Peggy Pay",
								"slug" => "peggypay"
							],
									];

			$this->host = $this->giveHost(parse_url(get_site_url(), PHP_URL_HOST));

			// $this->scriptsUrl = plugins_url("/assets/js/scripts-admin.js", __FILE__); // TODO .min
			$this->scriptsUrlFrontend = plugins_url("/assets/js/scripts-frontend.js", __FILE__); // TODO .min
			$this->stylesUrl = plugins_url("/assets/styles/styles.css", __FILE__);

			add_shortcode("peggyforms", function($attributes) {
				if (!$this->frontEndLoaded) {
					$this->frontEndLoaded = true;

					wp_enqueue_script("PeggyFormFrontendScript", $this->scriptsUrlFrontend);
				}

				$key = sanitize_key($attributes["key"]);

				if (empty($key) || !preg_match("/^[\d\w]+$/i", $key)) {
					return "";
				}

				$dataOptionsAttr = Array();
				$dataOptionsUrl = Array();

				$autoFocus = "false";
				// if (isset($attributes["autofocus"]) && in_array(strtolower($attributes["autofocus"]), [ "true", "false"] ) ) {
				// 	$autoFocus = $attributes["autofocus"];
				// 	$dataOptionsAttr[] = "autoFocus='". $attributes["autofocus"]. "'";
				// 	$dataOptionsUrl[] = "autoFocus=". $attributes["autofocus"];
				// }

				$editLink = "";

				if (is_user_logged_in()) {
					$aStyle = "style='text-decoration: none; margin-left: 15px;'";

					$editLink .= "<div style='text-align:right; padding: 5px; background:rgba(255,255,255,.1); border:1px solid rgba(255,255,255,.2);'>";
					$editLink .= "<a $aStyle target='_blank' href='". esc_url($this->getAppBaseUrl(). "/editor/formKey=". $key). "'>Edit this form</a>";
					$editLink .= "<a $aStyle target='_blank' href='". esc_url($this->getAppBaseUrl(). "/submissions/formKey=". $key). "'>View submissions</a>";
					$editLink .= "</div>";
				}

				$dataOptionsAttr[] = "peggy-embed-version=\"". self::EmbedScriptVersion. "\"";
				$dataOptionsUrl[] = "peggy-embed-version=". self::EmbedScriptVersion;

				$dataOptionsUrl = count($dataOptionsUrl) > 0 ? "?". implode("&", $dataOptionsUrl) : "";
				$dataOptionsAttr = count($dataOptionsAttr) > 0 ? " ". implode(" ", $dataOptionsAttr) : "";

				$style = isset($attributes["style"]) ? $attributes["style"] : "embed";

				$script = "
					<script>(function(w,d,fk,v,p) {
					var _tmp = document.getElementsByTagName('script');
					var t = _tmp[_tmp.length - 1];
					w.PeggyForms = {init: function() {w.PeggyForms.Embed.create( w, d, v, p, t );}};})
					(window, document, '". $key. "', ". self::EmbedScriptVersion. ", { style: '". $style. "' } );
					</script>
					<script src='". $this->getAppBaseUrlViewer(). "/". $key. "/js' async></script>
				";

				return $script. $editLink;
			});

			$submissionCache = null;
			add_shortcode("peggyvalue", function($attributes) use (&$submissionCache) {
				$field = sanitize_key($attributes["field"]);

				if (empty($field) || !preg_match("/^[\d\w]+$/i", $field)) {
					return "";
				}

				$error = null;

				if ($submissionCache === null) {
					$submissionCache = $this->api("Formbuilder.Submissions.getByHash", [ "hash" => get_query_var("peggyHash") ]);

					if (!$submissionCache || !$submissionCache->success || !$submissionCache->data) {
						$error = "failed to load the submission";
					}
				}

				if (!isset($submissionCache->data->$field)) {
					$error = "tag not found ($field)";
				}

				if (!!$error) {
					$value = "<span style='color:red;background:white;'>". $this->getAppName(). " error: ". $error. "</span>";
				} else {
					$value = $submissionCache->data->$field;
				}

				return $value;
			});

			add_action("init", function() {

				add_filter( 'query_vars', function($query_vars) { $query_vars[] = "peggyHash"; return $query_vars; });

				add_filter("mce_external_plugins", function($plugins) {
					$plugins["peggyforms"] = plugins_url("/assets/js/tinymce/plugin.js", __FILE__);

					return $plugins;
				});

				add_filter( 'media_buttons_context', function( $context ) {
				 	$icon = "<img src='". plugins_url("/assets/js/tinymce/get-form.png", __FILE__). "' />";

				 	$buttonForm = " <a class='button' onclick='tinyMCE.activeEditor.plugins.peggyforms.selectForm();'>$icon ". __("Add form", "Add form"). "</a>";
				 	$buttonField = " <a class='button' onclick='tinyMCE.activeEditor.plugins.peggyforms.selectField();'>$icon ". __("Add field", "Add field"). "</a>";

				 	return $context. $buttonForm. $buttonField;
				} );

				// Gutenberg
				add_action( 'enqueue_block_editor_assets', function() use (&$singleton) {
					wp_register_script(
						'peggyforms-gutenberg',
						plugins_url( "/assets/js/gutenberg.js", __FILE__),
						[ "wp-blocks", "wp-element", "wp-components", "wp-i18n" ]
					);

					wp_register_style(
						'peggyforms-gutenberg-style',
						plugins_url( '/assets/styles/gutenberg.css', __FILE__),
						[ 'wp-edit-blocks' ]
					);

					wp_enqueue_script( 'peggyforms-gutenberg' );
					wp_enqueue_style( 'peggyforms-gutenberg-style' );

					$forms = $this->getForms();
					wp_localize_script( 'peggyforms-gutenberg', 'peggyFormsGutenberg', [
						'forms' => $forms,
						'siteUrl' => get_home_url(),
						'block_logo'     => $this->getAppBaseUrl(). '/ThemeFolder/logo.svg',
						// 'thumbnail_logo' => $thumbnail_logo
					] );
					wp_enqueue_style( 'ninja-forms-block-style' );
					wp_enqueue_style( 'ninja-forms-block-editor' );

					register_block_type("peggyforms-gutenberg/form", [ "editor_script" => "peggyforms-gutenberg" ]);
					// register_block_type("peggyforms-gutenberg/formdynamic", [ "render_callback" => "peggyforms-gutenberg" ]);
				} );
			});

			add_action("admin_menu", function() {
				$templateRoot = plugin_dir_path(__FILE__). "templates/";

				$openSettings = function() use ($templateRoot) {
					global $peggyForms;
					require $templateRoot. "settings.php";
				};

				$iconData = 'data:image/png;base64,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';

				add_menu_page("PeggyForms", $this->getAppName("Peggy Forms"), "manage_options", "peggyFormsSettings", $openSettings, $iconData, 10);

				add_submenu_page("peggyFormsSettings", "Settings", "Settings", "manage_options", "peggyFormsSettings", $openSettings);

				if ($this->hasValidKey()) {
					global $submenu;
					$submenu["peggyFormsSettings"][] = [ "Editor", "manage_options", $this->getAppBaseUrl(). "/editor" ];
					$submenu["peggyFormsSettings"][] = [ "Submissions", "manage_options", $this->getAppBaseUrl(). "/submissions" ];

				}

				if (!$this->hasValidKey()) {
					global $submenu;
					$submenu["peggyFormsSettings"][] = [ "Register", "manage_options", $this->getAppBaseUrl(). "/plans" ];
				}
			});

			add_action("admin_head", function() {
				echo "<script type='text/javascript'>jQuery(document).ready( function(jqd) { jqd(\"ul#adminmenu a[href^='". $this->getAppBaseUrl(). "']\").attr( 'target', '_blank' ); });</script>";
			});

			add_action("admin_post_fb_saveSettings", array($this, "saveSettings"));
			add_action("wp_ajax_fb_updateDomainAssignment", array($this, "updateDomainAssignment"));

			add_action("wp_ajax_fb_get_forms", function() {
				echo json_encode($this->getForms());

				wp_die();
			});

			add_action("wp_ajax_fb_get_fields", function($formKey) {
				$formKey = $_REQUEST["formKey"];
				echo json_encode($this->getFormFields($formKey));

				wp_die();
			});
		}

		public function hasValidKey() {
			return !empty($this->key);
		}

		public function setKey($key) {
			$this->key = $key;
		}

		public function setApp($app) {
			$this->app = $app;
		}

		protected function api($action, $params, $usePublic = false) {
			if (empty($this->key)) {
				return null;
			}

			// First get access token if not available
			if (empty($this->accessToken) && !$usePublic) {
				$result = $this->api("Framework.authorize", [ "method" => "apiKey", "apiKey" => $this->key ], true);

				if ($result && $result->success) {
					$this->accessToken = $result->data->Token;
				} else {
					return false;
				}
			}

			$params["domain"] = $this->host;
			$params["token"] = $this->accessToken;

			$url = $this->getAppBaseUrl(). "/api/". $action;
			$debugUri = $url. "?". http_build_query($params);

			$error = false;

			$response = wp_remote_post($url, [
				"body" => $params,
				"timeout" => 5
			]);

			$rawResult = wp_remote_retrieve_body($response);

			$result = null;

			if (wp_remote_retrieve_response_code($response) !== 200) {
				$error = wp_remote_retrieve_response_message($response);
			} else {
				$result = json_decode($rawResult);

				if ($result === null) {
					$error = "JSON encoding error\n". $rawResult;
				} elseif ($result->success !== true) {
					$error = $result->message. " - ". $result->error;
				}
			}

			if ($error !== false) {
				echo $this->error($error);
				return null;
			} else {
				return $result;
			}
		}

		public function error($msg) {
			echo "<div style='padding:30px; color:red; background:#ffeded; border:1px solid #fdd; margin:20px 0;'>There is an error while connecting to ". $this->getAppName(). ". The error message is ". $msg. "</div>";
		}

		public function getMainDomain($domain) {
			// Only main domain
			// $domain = "local.nl";
			$domainParts = parse_url($domain);
			if (isset($domainParts["host"])) {
				$domain = $domainParts["host"];
			}

			$domainParts = explode(".", $domain);
			if (count($domainParts) > 1) {
				$domain = $domainParts[count($domainParts) - 2]. ".". $domainParts[count($domainParts) - 1];
			}

			return $domain;
		}

		public function isFormEnabledForThisDomain($form) {
			$domains = isset($form->Limitations->domains) && is_array($form->Limitations->domains) ? $form->Limitations->domains : [];
			return in_array($this->getMainDomain($this->host), $domains);
		}

		public function getForms() {
			$result = $this->api("Formbuilder.getForms", Array(/*"userHash" => $this->key, */"grouped" => false, "plugin" => "wordpress"));

			if (isset($result->data->forms) && is_array($result->data->forms)) {
				array_walk($result->data->forms, function($form) {
					$form->isEnabledForThisDomain = $this->isFormEnabledForThisDomain($form);
				});

				usort($result->data->forms, function($form) {
					return ($form->isEnabledForThisDomain ? 0 : 1). "-". $form->Name;
				});
			}

			return !$result ? null : $result->data;
		}

		public function getFormFields($formKey) {
			$result = $this->api("Formbuilder.getFormElements", Array("formKey" => $formKey, "plugin" => "wordpress"));

			return !$result ? null : $result->data;
		}

		public function saveSettings() {
			$apiKey = $_POST["apiKey"]; $apiKey = sanitize_option("peggyForms.apiKey", $apiKey);
			$app = $_POST["app"]; $app = sanitize_option("peggyForms.app", $app);
			$url = $_POST["redirectTo"]; $url = esc_url($url);


			if (!empty($apiKey) && preg_match("/^[\d\w]+$/i", $apiKey)) {
				update_option("peggyForms.apiKey", $apiKey);
			} else {
				delete_option("peggyForms.apiKey");
			}

			if (!empty($app) && preg_match("/^[\d\w]+$/i", $app)) {
				update_option("peggyForms.app", $app);
			} else {
				delete_option("peggyForms.app");
			}

			header("Location: $url");
			exit;
		}

		public function updateDomainAssignment() {
			$formKey = $_POST["formKey"];
			$assign = $_POST["assign"];
			$url = $_POST["redirectTo"]; $url = esc_url($url);

			if (!in_array($assign, ["add" , "remove" ])) return false;

			if (empty($formKey) || !preg_match("/^[\d\w]+$/i", $formKey)) {
				return false;
			}

			$result = $this->api("Formbuilder.assignDomain", [ "formKey" => $formKey, /*"userHash" => $this->key, */"assignDomain" => $assign, "host" => $this->host ]);
		}
	}

	$peggyForms = new PeggyForms();
	$peggyForms->setKey(get_option("peggyforms.apiKey"));
	$peggyForms->setApp(get_option("peggyforms.app"));