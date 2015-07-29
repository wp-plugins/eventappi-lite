<?php
namespace EventAppi\Helpers;

use CMB_Field;

class CMBGlobal extends CMB_Field
{
	public function save($post_id, $values)
	{
		// Do not save the values in WooCommerce as well (it's redundant)
		if (get_post_type($post_id) == 'product') {
			return;
		}

		// Don't save readonly values.
		if ($this->args['readonly']) {
			return;
		}

		$this->values = $values;
		$this->parse_save_values();

		// Allow override from args
		if (! empty($this->args['save_callback'])) {
			call_user_func($this->args['save_callback'], $this->values, $post_id);
			return;
		}

		// If we are not on a post edit screen
		if (! $post_id) {
			return;
		}

		delete_post_meta($post_id, str_replace('[]', '', $this->id));

		foreach ($this->values as $v) {
			$this->value = $v;
			$this->parse_save_value();

			if ($this->value || $this->value === '0') {
				add_post_meta($post_id, str_replace('[]', '', $this->id), $this->value);
			}
		}
	}
}