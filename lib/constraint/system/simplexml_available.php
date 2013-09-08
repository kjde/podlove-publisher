<?php
namespace Podlove\Constraint\System;

use Podlove\Constraint\Constraint;
use Podlove\Model;

class SimplexmlAvailable extends Constraint {

	const SCOPE = 'system';
	const SEVERITY = Constraint::SEVERITY_CRITICAL;	

	public function the_title() {
		echo __('The PHP simplexml extension must be installed.', 'podlove');
	}

	public function the_description() {
		?>
		<p>
			<?php
			echo __('The PHP simplexml extension must be installed on your server. You may need to recompile PHP. If you don\'t know how to do this, please contact your hoster.', 'podlove');
			?>
		</p>
		<p>
			<?php echo __('Helpful resources:') ?>
			<ul>
				
				<li><a href="http://php.net/manual/en/book.simplexml.php">simplexml book</a> [php.net]</li>
				<li><a href="http://stackoverflow.com/questions/1724511/how-to-check-where-apache-is-looking-for-a-php-ini-file">How to check where Apache is looking for a php.ini file?</a> [stackoverflow.com]</li>
			</ul>
		</p>
		<?php
	}

	public function isValid() {
		return in_array('SimpleXML', get_loaded_extensions());
	}
}