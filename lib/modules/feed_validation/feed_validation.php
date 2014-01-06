<?php
namespace Podlove\Modules\FeedValidation;
use Podlove\Log;
use Podlove\Model;

class Feed_Validation extends \Podlove\Modules\Base {

	protected $module_name = 'Feed Validation';
	protected $module_description = 'Automatically validate feeds once in a while.';
	protected $module_group = 'system';

	public function load() {
		add_action( 'podlove_module_was_activated_feed_validation', array( $this, 'was_activated' ) );
		add_action( 'podlove_module_was_deactivated_feed_validation', array( $this, 'was_deactivated' ) );
		add_action( 'podlove_feed_validation', array( $this, 'do_validations' ) );
		add_action( 'podlove_module_before_settings_feed_validation', function () {
			if ( $timezone = get_option( 'timezone_string' ) )
				date_default_timezone_set( $timezone );
			?>
			<div>
				<em>
					<?php
					echo sprintf(
						__( 'Next scheduled validation: %s' ),
						date( get_option('date_format') . ' ' . get_option( 'time_format' ), wp_next_scheduled( 'podlove_feed_validation' ) )
					);
					?>
				</em>
			</div>
			<?php
		} );
	}

	public function was_activated( $module_name ) {
		if ( ! wp_next_scheduled( 'podlove_feed_validation' ) )
			wp_schedule_event( time(), 'twicedaily', 'podlove_feed_validation' );
	}

	public function was_deactivated( $module_name ) {
		wp_clear_scheduled_hook( 'podlove_feed_validation' );
	}

	/**
	 * Main Cron function call.
	 */
	public function do_validations() {

		set_time_limit( 1800 ); // set max_execution_time to half an hour

		Log::get()->addInfo( 'Begin scheduled feed validation.' );

		foreach ( \Podlove\Model\Feed::all() as $feed_key => $feed) {
			// Performing validation and log the errors
			$feed->logValidation( $feed->getValidationErrorsandWarnings() );
			// Refresh the transient
			set_transient( 'podlove_dashboard_feed_validation_' . $feed->id, 
											  $feed->getValidationIcon(),
											  3600*24 );
		}

		Log::get()->addInfo( 'End scheduled feed validation.' );
	}
	
}