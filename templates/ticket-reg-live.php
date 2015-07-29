<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ($data['access_error']) { ?>
	<div class="ea-note-error"><?php echo $data['access_error']; ?></div>
	<?php
} elseif ($data['already_sent']) { ?>
	<div class="ea-note-ok">
		<?php echo $data['already_sent']; ?>
	</div>
<?php
} else {
    if ($data['reg_success']) { ?>
		<div class="ea-note-ok">
			<?php echo $data['reg_success']; ?>
		</div>
	<?php
	} else {
        if (! empty($data['submit_errors'])) { ?>
			<div class="ea-note-error">
				<ul>
					<?php
					foreach ($data['submit_errors'] as $error) {
						?>
						<li><?php echo $error; ?></li>
						<?php
					}
					?>
				</ul>
			</div>
			<?php
		}

		// There are fields to show
		if (! empty($data['fields'])) {
			?>
				<h4><em>
				<?php
				if ($data['ea_status'] == 'assign') {
					echo 'Assign Ticket';
				} elseif ($data['ea_status'] == 'claim') {
					echo 'Claim Ticket';
				}
				?>
				</em></h4>
			<?php

			// Ticket ID was appended - Show basic information
			if ($data['ticket_name'] != '') {
				?>
				<div class="ea-note-ok">
					<ul>
						<?php if ($data['event_name']) { ?>
							<li><?php _e('Event:', EVENTAPPI_PLUGIN_NAME); ?>
								<strong><?php echo $data['event_name']; ?></strong></li>
							<?php
						}
						?>
						<li><?php _e('Ticket:', EVENTAPPI_PLUGIN_NAME); ?>
							<strong><?php echo $data['ticket_name']; ?></strong></li>
					</ul>
				</div>
				<?php
			}
			?>
			<form action="" name="ea-ticket-reg" method="post" enctype="multipart/form-data">
				<input type="hidden" name="ea_ticket_id" value="<?php echo $data['ea_ticket_id']; ?>"/>
				<input type="hidden" name="ea_reg_id" value="<?php echo $data['ea_reg_id']; ?>"/>
				<input type="hidden" name="ea_reg_code" value="<?php echo $data['ea_reg_code']; ?>"/>
				<input type="hidden" name="ea_status" value="<?php echo $data['ea_status']; ?>" />

				<div id="ea-reg-fields-wrap">
					<?php
					// $iName = Field Key, $name = Input Field Name
					foreach ($data['fields'] as $iName => $val) {
						$id        = $val['id'];
						$name      = $val['name'];
						$title     = $val['title'];
						$type      = $val['type'];
						$typeAttr  = $val['type_attr'];
						$req       = $val['req'];
						$attrsList = $val['attrs_list'];
						$options   = $val['options'];
						?>
						<div class="field-wrap">
							<?php
							if ($val['no_label'] === false) {
								?>
								<label for="<?php echo $id; ?>"><?php echo $title; ?>
									<?php if ($req == 1) { ?><em class="req">*</em><?php } ?>
								</label>
								<?php
							} else {
								?>
								<div><?php echo $title; ?> <?php if ($req == 1) { ?><em class="req">*</em><?php } ?>
								</div>
								<?php
							}

							// Input & Input (Date)
							if (in_array($type, array('input_text', 'input_date', 'input_email'))) {
								?>
								<div>
									<input <?php echo $attrsList; ?>
										type="<?php echo $typeAttr; ?>" name="<?php echo $name; ?>"
										id="<?php echo $id; ?>" value="<?php echo $data['post'][$iName]; ?>" />
								</div>
								<?php
								// Select & Multiple Selections
							} elseif (in_array($type, array('select', 'select_m'))) {
								?>
								<div>
									<select <?php echo $attrsList; ?> name="<?php echo $name; ?>"
									        id="<?php echo $id; ?>">
										<option value=""
										        style="color: #777;"><?php _e('[select]', EVENTAPPI_PLUGIN_NAME); ?></option>
										<?php
										if (! empty($options)) {
											foreach ($options as $option) {
												?>
												<option <?php if ($option == $data['post'][$iName]) {
													echo 'selected="selected"';
												} ?> value="<?php echo $option; ?>"><?php echo $option; ?></option>
												<?php
											}
										}
										?>
									</select>
								</div>
								<?php
								// Textarea
							} elseif ($type == 'textarea') {
								?>
								<div><textarea <?php echo $attrsList; ?> name="<?php echo $name; ?>"
									id="<?php echo $id; ?>"><?php echo $data['post'][ $iName ]; ?></textarea>
								</div>
								<?php
							} elseif ($type == 'radio') {
								// Radios
								echo $val['radios_area'];
							} elseif ($type == 'checkbox') {
								// Checkboxes
								echo $val['checkboxes_area'];
							} elseif ($type == 'file') {
								?>
								<div>
									<input <?php echo $attrsList; ?> type="file" name="<?php echo $name; ?>"
										id="<?php echo $id; ?>"/>&nbsp; <a href="#" class="ea-reg-file-clear"><?php _e('(Clear)', EVENTAPPI_PLUGIN_NAME); ?></a>
								</div>
								<?php
							}
							?>
						</div>
						<?php
					}
					?>
					<input type="submit" name="ticket-reg-submit"
					       value="<?php echo esc_attr(__('Register Ticket', EVENTAPPI_PLUGIN_NAME)); ?>"/>
				</div>
			</form>
			<?php
		}
	}
}
