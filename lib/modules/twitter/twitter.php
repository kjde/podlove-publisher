<?php
namespace Podlove\Modules\Twitter;

use \Podlove\Model;
use \Podlove\Http;

class Twitter extends \Podlove\Modules\Base
{

    protected $module_name = 'Twitter';
    protected $module_description = 'Announces new podcast episodes on Twitter';
    protected $module_group = 'external services';

    public function load()
    {

        $module_url = $this->get_module_url();
        $user = null;

        add_action('podlove_module_was_activated_twitter', array($this, 'was_activated'));

        add_action( 'wp_ajax_podlove-twitter-post', array( $this, 'ajax_post_to_twitter' ) );

        if ($this->get_module_option('twitter_auth_key') !== "") {
            add_action('publish_podcast', array($this, 'post_to_twitter_handler'));
            add_action('delayed_twitter_post', array($this, 'post_to_twitter_delayer'), 10, 2);
        }

        if (isset($_GET["page"]) && $_GET["page"] == "podlove_settings_modules_handle") {
            add_action('admin_bar_init', array($this, 'reset_twitter_auth'));
        }

        // Import all posts as already published
        add_filter(
            'wp_import_post_meta',
            function ($postmetas, $post_id, $post) {
                $postmetas[] = array(
                    'key' => '_podlove_episode_was_published_on_twitter',
                    'value' => true
                );

                return $postmetas;
            },
            10,
            3
        );

        if ($this->get_module_option('twitter_auth_key') == "") {
            $description = '<i class="podlove-icon-remove"></i> '
                . __(
                    'You need to allow Podlove Publisher to access your App.net account. To do so please start the authorization process, follow the instructions and paste the obtained code in the field above.',
                    'podlove'
                )
                . '<br><a href="http://dev.kjanssen.net/auth.podlove.org/twitter.php?step=1" class="button button-primary" target="_blank">' . __(
                    'Start authorization process now',
                    'podlove'
                ) . '</a>';
            $this->register_option(
                'twitter_auth_key',
                'string',
                array(
                    'label' => __('Authorization', 'podlove'),
                    'description' => $description,
                    'html' => array('class' => 'regular-text', 'placeholder' => 'Twitter authentication code')
                )
            );
        } else {

            if ($user = $this->fetch_authorized_user()) {
                $description = '<i class="podlove-icon-ok"></i> '
                    . sprintf(
                        __('You are logged in as %s. If you want to logout, click %shere%s.', 'podlove'),
                        '<strong>' . $user->name . ' (' . $user->screen_name . ')</strong>',
                        '<a href="' . admin_url(
                            'admin.php?page=podlove_settings_modules_handle&reset_twitter_auth_code=1'
                        ) . '">',
                        '</a>'
                    );
            } else {
                $description = '<i class="podlove-icon-remove"></i> '
                    . sprintf(
                        __(
                            'Something went wrong with the Twitter connection. Please start the authorization process again. To do so click %shere%s',
                            'podlove'
                        ),
                        '<a href="' . admin_url(
                            'admin.php?page=podlove_settings_modules_handle&reset_twitter_auth_code=1'
                        ) . '">',
                        '</a>'
                    );

            }
            $this->register_option(
                'twitter_auth_key',
                'hidden',
                array(
                    'label' => __('Authorization', 'podlove'),
                    'description' => $description,
                    'html' => array('class' => 'regular-text')
                )
            );




            $twitter_post_delay_hours = str_pad(
                $this->get_module_option('twitter_post_delay_hours'),
                2,
                0,
                STR_PAD_LEFT
            );
            $twitter_post_delay_minutes = str_pad(
                $this->get_module_option('twitter_post_delay_minutes'),
                2,
                0,
                STR_PAD_LEFT
            );

            $this->register_option( 'twitter_automatic_announcement', 'checkbox', array(
                    'label'       => __( 'Automatic Announcement', 'podlove' ),
                    'description' => 'Announces new podcast episodes on Twitter'
                ) );

            $this->register_option(
                'twitter_post_delay',
                'callback',
                array(
                    'label' => __('Post delay', 'podlove'),
                    'callback' => function () use ($twitter_post_delay_hours, $twitter_post_delay_minutes) {
                            ?>
                            <input type="text" name="podlove_module_app_dot_net[twitter_post_delay_hours]"
                                   id="podlove_module_app_dot_net_twitter_post_delay_hours"
                                   value="<?php echo($twitter_post_delay_hours ? $twitter_post_delay_hours : ''); ?>"
                                   class="regular-text" placeholder="00">
                            <label for="podlove_module_app_dot_net_twitter_post_delay_hours">Hours</label>
                            <input type="text" name="podlove_module_app_dot_net[twitter_post_delay_minutes]"
                                   id="podlove_module_app_dot_net_twitter_post_delay_minutes"
                                   value="<?php echo($twitter_post_delay_minutes ? $twitter_post_delay_minutes : ''); ?>"
                                   class="regular-text" placeholder="00">
                            <label for="podlove_module_app_dot_net_twitter_post_delay_minutes">Minutes</label>
                        <?php
                        }
                )
            );

            $description = '';
            if ($this->get_module_option('twitter_poster_announcement_text') == "") {
                $description = '<i class="podlove-icon-remove"></i>'
                    . __('You need to set a text to announce new episodes.', 'podlove');
            }

            $description .= __(
                'Twitter allows 140 characters per post. Try to keep the announcement text short. Your episode titles will need more space than the placeholders.',
                'podlove'
            );

            $description .= '
					' . __('Use these placeholders to customize your announcement', 'podlove') . ':
					<code title="' . __('The title of your podcast', 'podlove') . '">{podcastTitle}</code>
					<code title="' . __('The title of your episode, linking to it', 'podlove') . '">{linkedEpisodeTitle}</code>
					<code title="' . __('The title of the episode', 'podlove') . '">{episodeTitle}</code>
					<code title="' . __('The permalink of the current episode', 'podlove') . '">{episodeLink}</code>
					<code title="' . __('The subtitle of the episode', 'podlove') . '">{episodeSubtitle}</code>';

            $this->register_option(
                'twitter_poster_announcement_text',
                'text',
                array(
                    'label' => __('Announcement text', 'podlove'),
                    'description' => $description,
                    'html' => array(
                        'cols' => '50',
                        'rows' => '4',
                        'placeholder' => __('Check out the new {podcastTitle} episode: {linkedEpisodeTitle}', 'podlove')
                    )
                )
            );

            $this->register_option(
                'twitter_preview',
                'callback',
                array(
                    'label' => __('Announcement preview', 'podlove'),
                    'callback' => function () use ($user, $module_url) {

                            if (!$user) {
                                return;
                            }

                            $podcast = Model\Podcast::get_instance();
                            if ($episode = Model\Episode::find_one_by_where('slug IS NOT NULL')) {
                                $example_data = array(
                                    'episode' => get_the_title($episode->post_id),
                                    'episode-link' => get_permalink($episode->post_id),
                                    'subtitle' => $episode->subtitle
                                );
                            } else {
                                $example_data = array(
                                    'episode' => 'My Example Episode',
                                    'episode-link' => 'http://www.example.com/episode/001',
                                    'subtitle' => 'My Example Subtitle'
                                );
                            }
                            ?>
                            <div id="podlove_twitter_post_preview"
                                 data-podcast="<?php echo $podcast->title ?>"
                                 data-episode="<?php echo $example_data['episode'] ?>"
                                 data-episode-link="<?php echo $example_data['episode-link'] ?>"
                                 data-episode-subtitle="<?php echo $example_data['subtitle'] ?>">
                                <div class="twitter avatar"
                                     style="background-image:url(<?php echo $user->profile_image_url ?>);"></div>
                                <div class="twitter content">
                                    <div class="twitter username"><?php echo $user->name ?></div>
                                    <div class="twitter body">Lorem ipsum dolor ...</div>

                                    <div class="twitter footer">
                                        <ul>
                                            <li>
                                                <i class="podlove-icon-time"></i> now
                                            </li>
                                            <li>
                                                <i class="podlove-icon-reply"></i> Reply
                                            </li>
                                            <li>
                                                <i class="podlove-icon-share"></i> via Podlove Publisher
                                            </li>
                                        </ul>
                                    </div>
                                </div>

                                <div style="clear: both"></div>
                            </div>

                            <script type="text/javascript" src="<?php echo $module_url ?>/twitter.js"></script>
                            <link rel="stylesheet" type="text/css" href="<?php echo $module_url ?>/twitter.css"/>
                        <?php
                        }
                )
            );


            $this->register_option( 'twitter_manual_post', 'callback', array(
                    'label' => __( 'Manual Announcement', 'podlove' ),
                    'callback' => function() {
                            $episodes = Model\Episode::all();
                            ?>
                            <select id="twitter_manual_post_episode_selector" class="chosen">
                                <?php
                                foreach ( $episodes as $episode ) {
                                    $post = get_post( $episode->post_id );
                                    if ( $post->post_status == 'publish'  )
                                        echo "<option value='" . $episode->post_id . "'>" . $post->post_title . "</option>";
                                }
                                ?>
                            </select>
                            <span class="button" id="twitter_manual_post_alpha">
								Announce, as configured
								<span class="twitter-post-status-pending">
									<i class="podlove-icon-spinner rotate"></i>
								</span>
								<span class="twitter-post-status-ok">
									<i class="podlove-icon-ok"></i>
								</span>
							</span>
                        <?php
                        }
                ) );

        }
    }

    public function was_activated()
    {
        $episodes = Model\Episode::all();
        foreach ($episodes as $episode) {
            $post = get_post($episode->post_id);
            if ($post->post_status == 'publish' && !get_post_meta(
                    $episode->post_id,
                    '_podlove_episode_was_published_on_twitter',
                    true
                )
            ) {
                update_post_meta($episode->post_id, '_podlove_episode_was_published_on_twitter', true);
            }
        }
    }


    /**
     * Fetch name of logged in user via twitter API.
     *
     * Cached in transient "podlove_twitter_username".
     *
     * @return string
     */
    public function fetch_authorized_user()
    {

        $cache_key = 'podlove_twitter_user';

        if (($user = get_transient($cache_key)) !== false) {
            return $user;
        } else {
            if (!($token = $this->get_module_option('twitter_auth_key'))) {
                return false;
            }


            $response = $this->podloveCall('verify_credentials', '');


            $decoded_result = json_decode($response);


            if (isset($decoded_result->errors)) {
                echo $decoded_result->errors['0']->message;
                \Podlove\Log::get()->addInfo(
                    'Twitter API: ' . $decoded_result->errors['0']->message . ' (' . $decoded_result->errors['0']->code . ')'
                );
            } else {
                $user = $decoded_result ? $decoded_result : false;
                set_transient($cache_key, $user, 60 * 60 * 24 * 365); // 1 year, we devalidate manually
            }


            return $user;

        }

        return false;
    }

    private function is_already_published($post_id)
    {
        return get_post_meta($post_id, '_podlove_episode_was_published_on_twitter', true);
    }

    public function post_to_twitter($post_id)
    {

        $episode_text = $this->get_text_for_episode( $post_id );

        $text = $episode_text['text'];


        $response = $this->podloveCall('statuses_update', 'status='.$text);

        $decoded_result = json_decode($response);

        if (isset($decoded_result->errors)) {
            \Podlove\Log::get()->addInfo(
                'Twitter API: ' . $response
            );
        } else {
            \Podlove\Log::get()->addInfo(
                'Twitter API: status update published'
            );

            update_post_meta($post_id, '_podlove_episode_was_published_on_twitter', true);
        }
    }

    public function replace_tags( $post_id ) {
        $selected_role = $this->get_module_option('twitter_contributor_filter_role');
        $selected_group = $this->get_module_option('twitter_contributor_filter_group');

        $text = $this->get_module_option('twitter_poster_announcement_text');
        $episode = \Podlove\Model\Episode::find_or_create_by_post_id( $post_id );
        $podcast = Model\Podcast::get_instance();
        $post = get_post( $post_id );
        $post_title = $post->post_title;

        $text = str_replace("{podcastTitle}", $podcast->title, $text);
        $text = str_replace("{episodeTitle}", $post_title, $text);
        $text = str_replace("{episodeLink}", get_permalink( $post_id ), $text);
        $text = str_replace("{episodeSubtitle}", $episode->subtitle, $text);

        $posted_linked_title = array();
        $start_position = 0;

        while ( ($position = \Podlove\strpos( $text, "{linkedEpisodeTitle}", $start_position, "UTF-8" )) !== FALSE ) {
            $length = \Podlove\strlen( $post_title, "UTF-8" );
            $episode_entry = array(
                "url"  => get_permalink( $post_id ),
                "text" => $post_title,
                "pos"  => $position,
                "len"  => ($position + $length <= 256) ? $length : 256 - $position
            );
            array_push( $posted_linked_title, $episode_entry );
            $start_position = $position + 1;
        }

        $text = str_replace("{linkedEpisodeTitle}", $post_title, $text);
        $text = apply_filters( 'podlove_twitter_tags', $text, $post_id, $selected_role, $selected_group );

        return array(
            'text' => $text,
            'posted_linked_title' => $posted_linked_title
        );
    }

    private function get_text_for_episode($post_id) {
        $post = $this->replace_tags( $post_id );

        if ( \Podlove\strlen( $post['text'] ) > 256 )
            $post['text'] = \Podlove\substr( $post['text'], 0, 255 ) . "…";

        return array(
            'text' => $post['text'],
            'link_annotation' => $post['posted_linked_title']
        );
    }


    public function reset_twitter_auth()
    {
        if (isset($_GET["reset_twitter_auth_code"]) && $_GET["reset_twitter_auth_code"] == "1") {
            $this->update_module_option('twitter_auth_key', "");
            delete_transient('podlove_twitter_user');
            delete_transient('podlove_twitter_rooms');
            delete_transient('podlove_twitter_broadcast_channels');
            header('Location: ' . get_site_url() . '/wp-admin/admin.php?page=podlove_settings_modules_handle');
        }
    }

    public function ajax_post_to_twitter() {
        if( !$_REQUEST['post_id'] )
            return;

        $this->post_to_twitter( $_REQUEST['post_id'] );
    }

    public function post_to_twitter_handler($postid)
    {
        if ( $this->is_already_published( $post_id ) || $this->get_module_option('twitter_automatic_announcement') !== 'on' )
            return;

        $post_id = $_POST['post_ID'];

        $twitter_post_delay_hours   = str_pad( $this->get_module_option('twitter_post_delay_hours'), 2, 0, STR_PAD_LEFT );
        $atwitter_post_delay_minutes = str_pad( $this->get_module_option('twitter_post_delay_minutes'), 2, 0, STR_PAD_LEFT );

        $delayed_time = strtotime( $twitter_post_delay_hours . $twitter_post_delay_minutes );
        $delayed_time_in_seconds = date("H", $delayed_time) * 3600 + date("i", $delayed_time) * 60;

        wp_schedule_single_event( time()+$delayed_time_in_seconds, "delayed_twitter_post", array( $post_id ) );

    }


    public function post_to_twitter_delayer($post_id, $post_title)
    {
        $this->post_to_twitter($post_id, $post_title);
    }



    private function podloveCall($action, $fields)
    {

        $token = array();
        parse_str($this->get_module_option('twitter_auth_key'), $token);


        $ch = curl_init(); // initiate curl
        //TODO: check url before commit!
        $url = "http://dev.kjanssen.net/auth.podlove.org/twitterApi.php"; // where you want to post data
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true); // tell curl you want to post something
        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            "oauth_token=" . $token['oauth_token'] . "&oauth_token_secret=" . $token['oauth_token_secret'] . "&action=" . $action . "&" . $fields
        ); // define what you want to post
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // return the output in string format
        $output = curl_exec($ch); // execute

        curl_close($ch); // close curl handle

        return $output;
    }


}