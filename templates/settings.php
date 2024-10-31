<?php
	if ( ! defined( 'ABSPATH' ) ) exit;

	global $peggyForms;

	wp_enqueue_style("PeggySettings", plugins_url( "/settings.css", __FILE__ ));
	wp_enqueue_script("PeggySettings", plugins_url( "/settings.js", __FILE__ ));
?>

<div class="wrap peggy-container">
	<?php if (!empty($peggyForms->getApp())) { ?><div class="peggy-header"><img src="<?php echo $peggyForms->getAppBaseUrl();?>/ThemeFolder/logo.svg" style="margin:20px; max-width:300px;" /></div><?php } ?>

	<div class="peggy-block">
		<form action="<?php echo esc_url( admin_url('admin-post.php'));?>" method="post">
			<input type="hidden" name="action" value="fb_saveSettings" />
			<input type="hidden" name="redirectTo" value="<?php echo menu_page_url("peggyFormsSettings", false); ?>" />
			<table>
				<tr>
					<th style="text-align:left; padding-right:1rem;">1. Choose your Peggy Suite application</th>
					<td style="padding-right:1rem;">
						<?php foreach($peggyForms->environments as $env) {
							$checked = $peggyForms->app === $env->slug ? "checked" : "";
							?>
							<input type="radio" name="app" value="<?php echo $env->slug; ?>" id="<?php echo "app-". $env->slug; ?>" <?php echo $checked; ?> />
							<label for="<?php echo "app-". $env->slug; ?>"><?php echo $env->name; ?></label>
						<?php } ?>
					</td>
					<td><button class="button button-primary">Save</button></td>
				</tr>
				<?php if (!empty($peggyForms->getApp())) { ?>
					<tr>
						<th style="text-align:left; padding-right:1rem;">2. Enter your API key</th>
						<td><input style="width:350px;" name="apiKey" value="<?php echo $peggyForms->key; ?>" /></td>
						<td><button class="button button-primary">Save</button></td>
						<?php
							if (!$peggyForms->hasValidKey()) {
								$url = $peggyForms->getAppBaseUrl(). "/plans";
								echo "<td> or <a href='$url' target='_blank'>sign up</a> now!</td>";
							}
						?>
					</tr>
				<?php } ?>
			</table>
		</form>
	</div>

	<?php
		if ($peggyForms->hasValidKey()) {
			// $apiKey = get_option("peggyForms.apiKey", "");
			// $peggyForms->setKey($apiKey);
			$formsResult = $peggyForms->getForms();

			if ($formsResult && isset($formsResult->forms)) {
				$forms = $formsResult->forms;
			} else {
				$forms = null;
				// $peggyForms->error("Get forms error");
			}

			if ($forms !== null && $forms !== false && is_array($forms)) {

				echo "<div class='peggy-block'>";

				if (count($forms) <= 0) {

					$domain = $peggyForms->host;

					echo "
						<p>Hi! ". $this->getAppName(). " couldn`t load any of your forms yet. No worries. To get your forms in wordpress you have to do one of the following things:</p>
						<ul class='peggylist'>
							<li>In <a href='". $this->getAppBaseUrl(). "/account#accountPlugins' target='_blank'>your account</a> -> plugins, enable the <strong>Expose all</strong> feature to expose all your forms.</li>
							<li>If you don`t want to expose all your forms, open the form you want to expose in <a href='". $this->getAppBaseUrl(). "/editor' target='_blank'>the editor</a>. There go to the [Share] tab -> [Domains] tab and add this domain:<br/>
								<input type='text' readonly value='$domain' onfocus='this.select()' /> <strong>Important</strong>: do not enter protocols (http / https) and subdomains.
							</li>
							<li>
								Or combine those two options for really simple managing which forms exposes. To do this, first execute step 1. Then enable all the forms you need here on this page. And as last disable the <strong>Expose all</strong> feature again.</li>
							</li>
						</ul>
					";
				} else {
					echo "<h3>Forms</h3>";

					class FormsTable extends WP_List_Table {

						function __construct($data, $exposeAllForms) {
							$this->items = $data;
							$this->exposeAllForms = $exposeAllForms;

							parent::__construct([
								"singular" => "Form",
								"plural" => "Forms",
								"ajax" => false
							]);
						}

						function get_columns() {
						  	$columns = array(
						    	'form' => 'Form',
						    	'edit'    => 'Edit',
						    	'submissions' => 'Submissions',
						    );

						    if ($this->exposeAllForms) {
						    	$columns["assignDomain"] = "Enable form on this site";
						    } else {
						    	$columns["assignDomain"] = "";
						    }

						    return $columns;
						}

						function prepare_items() {
							$columns = $this->get_columns();
							$hidden = array();
							$sortable = array();
							$this->_column_headers = array($columns, $hidden, $sortable);
						}

						function column_default( $item, $column_name ) {
							return $item[ $column_name ];
						}
					}

					$formsData = array_map(function($form) use ($peggyForms, $formsResult) {
						$form->form = $form->Name;
						$form->edit = "<a class='button button-primary' target='_blank' href='". esc_url($peggyForms->getAppBaseUrl(). "/editor/formKey=". $form->Key. "?token=". $peggyForms->accessToken). "'>Edit</a>";
						$form->submissions = "<a class='button button-primary' target='_blank' href='". esc_url($peggyForms->getAppBaseUrl(). "/submissions/formKey=". $form->Key. "?token=". $peggyForms->accessToken). "'>Submissions</a>";

						$domainAssigned = $peggyForms->isFormEnabledForThisDomain($form);

						$checked = $domainAssigned ? "checked='checked'" : "";

						if ($formsResult->exposeAllForms) {
							$form->assignDomain = "
								<label class='switch assign-domain'><input type='checkbox' name='assign' value='add' $checked data-form-key='{$form->Key}' /><span class='slider round'></span></label>
							";
						} else {
							$form->assignDomain = "
								<span class='button button-primary remove-domain' data-form-key='{$form->Key}'>Remove</span>
							";
						}

						return (array)$form;
					}, $forms);

					$formsTable = new FormsTable($formsData, $formsResult->exposeAllForms);
					$formsTable->prepare_items();


					$formsTable->display();
				}

				echo "</div>";
			}
		} elseif (!empty($peggyForms->getApp())) {
			echo "
				<div class='peggy-block'>
					<p>Hi! ". $this->getAppName(). " needs an active API key from you. Login into your <a href='". $this->getAppBaseUrl(). "/account/apikeys' target='_blank'>your account</a> and create one!</p>
				</div>
			";
		}
	?>
</div>