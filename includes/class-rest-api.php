<?php
/**
 * REST API Handler
 * Converts Vercel serverless functions to WordPress REST API endpoints
 */

if (!defined('ABSPATH')) {
    exit;
}

class Clubworx_REST_API {
    
    private static $instance = null;
    private $namespace = 'clubworx/v1';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
        add_action('init', array($this, 'register_shortcodes'));
    }

    /**
     * @return WP_REST_Response
     */
    private function rest_error_from_wp_error(WP_Error $err) {
        $data = $err->get_error_data();
        $status = is_array($data) && isset($data['status']) ? (int) $data['status'] : 400;
        return new WP_REST_Response(array(
            'error' => $err->get_error_code(),
            'message' => $err->get_error_message(),
        ), $status);
    }

    /**
     * Empty timetable structure matching front-end expectations.
     *
     * @return array<string,mixed>
     */
    private function empty_schedule_tree() {
        return array(
            'kids' => array(
                'under6' => array(),
                'over6' => array(),
            ),
            'adults' => array(
                'general' => array(),
                'foundations' => array(),
            ),
            'women' => array(),
        );
    }

    /**
     * @param array<string,array<string,mixed>> $slug_schedules slug => schedule
     * @return array<string,mixed>
     */
    private function merge_schedule_trees($slug_schedules) {
        if (!is_array($slug_schedules) || empty($slug_schedules)) {
            return $this->empty_schedule_tree();
        }
        if (count($slug_schedules) === 1) {
            $one = reset($slug_schedules);
            return is_array($one) ? $one : $this->empty_schedule_tree();
        }
        $out = $this->empty_schedule_tree();
        $all = Clubworx_Locations::all();
        foreach ($slug_schedules as $slug => $sch) {
            if (!is_array($sch)) {
                continue;
            }
            $label = isset($all[$slug]['label']) ? $all[$slug]['label'] : $slug;
            $prefix = '[' . $label . '] ';
            $this->merge_one_schedule_into($out, $sch, $prefix);
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $out
     * @param array<string,mixed> $sch
     */
    private function merge_one_schedule_into(&$out, $sch, $prefix) {
        foreach (array('kids', 'adults') as $cat) {
            if (!isset($sch[$cat]) || !is_array($sch[$cat])) {
                continue;
            }
            foreach ($sch[$cat] as $sub => $days) {
                if (!isset($out[$cat][$sub])) {
                    $out[$cat][$sub] = array();
                }
                if (!is_array($days)) {
                    continue;
                }
                foreach ($days as $day => $classes) {
                    if (!is_array($classes)) {
                        continue;
                    }
                    foreach ($classes as $c) {
                        $out[$cat][$sub][$day][] = $prefix . $c;
                    }
                }
            }
        }
        if (isset($sch['women']) && is_array($sch['women'])) {
            foreach ($sch['women'] as $day => $classes) {
                if (!is_array($classes)) {
                    continue;
                }
                if (!isset($out['women'][$day])) {
                    $out['women'][$day] = array();
                }
                foreach ($classes as $c) {
                    $out['women'][$day][] = $prefix . $c;
                }
            }
        }
    }
    
    /**
     * Register shortcodes
     */
    public function register_shortcodes() {
        add_shortcode('clubworx_timetable', array($this, 'timetable_shortcode'));
        add_shortcode('clubworx_pricing', array($this, 'pricing_shortcode'));
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Schedule endpoint
        register_rest_route($this->namespace, '/schedule-simple', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_schedule'),
            'permission_callback' => '__return_true',
            'args' => array(
                'account' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));
        // Backwards-compatible alias used in some docs/manual testing.
        register_rest_route($this->namespace, '/timetable', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_schedule'),
            'permission_callback' => '__return_true',
            'args' => array(
                'account' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));
        
        // Prospects endpoint (create contact in ClubWorx)
        register_rest_route($this->namespace, '/prospects', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_prospect'),
            'permission_callback' => '__return_true',
        ));
        
        // Events endpoint (find available classes)
        register_rest_route($this->namespace, '/events-simple', array(
            'methods' => 'POST',
            'callback' => array($this, 'find_events'),
            'permission_callback' => '__return_true',
        ));
        
        // Bookings endpoint (create booking in ClubWorx)
        register_rest_route($this->namespace, '/bookings', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_booking'),
            'permission_callback' => '__return_true',
        ));
        
        // Attribution tracking endpoint
        register_rest_route($this->namespace, '/attribution', array(
            'methods' => 'POST',
            'callback' => array($this, 'track_attribution'),
            'permission_callback' => '__return_true',
        ));
        
        // Test ClubWorx response structure endpoint
        register_rest_route($this->namespace, '/test-response', array(
            'methods' => 'GET',
            'callback' => array($this, 'test_clubworx_response'),
            'permission_callback' => '__return_true',
        ));
        
        // GA4 Measurement Protocol endpoint
        register_rest_route($this->namespace, '/ga4-measurement', array(
            'methods' => 'POST',
            'callback' => array($this, 'send_ga4_measurement'),
            'permission_callback' => '__return_true',
        ));
        
        // Clear schedule cache endpoint (admin only)
        register_rest_route($this->namespace, '/clear-cache', array(
            'methods' => 'POST',
            'callback' => array($this, 'clear_schedule_cache'),
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ));
        
        // Diagnostic endpoint to check configuration
        register_rest_route($this->namespace, '/diagnostics', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_diagnostics'),
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ));
        
        // Test ClubWorx API endpoint with raw response
        register_rest_route($this->namespace, '/test-clubworx-raw', array(
            'methods' => 'GET',
            'callback' => array($this, 'test_clubworx_raw'),
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ));
        
        // Debug class processing endpoint
        register_rest_route($this->namespace, '/debug-class-processing', array(
            'methods' => 'GET',
            'callback' => array($this, 'debug_class_processing'),
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ));
        
        // Booking details endpoint
        register_rest_route($this->namespace, '/booking-details', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_booking_details'),
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ));
        
        // Membership plans endpoint
        register_rest_route($this->namespace, '/membership-plans', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_membership_plans'),
            'permission_callback' => '__return_true',
            'args' => array(
                'account' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'refresh' => array(
                    'required' => false,
                    'type' => 'string',
                ),
            ),
        ));
        
        // Test email endpoint
        register_rest_route($this->namespace, '/test-email', array(
            'methods' => 'POST',
            'callback' => array($this, 'test_email'),
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ));
        
        // Check for updates endpoint
        register_rest_route($this->namespace, '/check-updates', array(
            'methods' => 'POST',
            'callback' => array($this, 'check_for_updates'),
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ));
    }
    
    /**
     * Get schedule data
     */
    public function get_schedule($request) {
        $account_raw = $request->get_param('account');
        $account_raw = is_string($account_raw) ? $account_raw : '';
        $slugs = Clubworx_Locations::expand_account_slugs($account_raw, null);

        $slug_schedules = array();
        foreach ($slugs as $slug) {
            $slug_schedules[$slug] = $this->get_clubworx_schedule($slug);
        }
        $schedule = $this->merge_schedule_trees($slug_schedules);

        $clubworx_configured = true;
        foreach ($slugs as $slug) {
            $loc = Clubworx_Locations::get($slug);
            if (!$loc || empty($loc['api_url']) || empty($loc['api_key'])) {
                $clubworx_configured = false;
                break;
            }
        }

        $first = $slugs[0];
        $cache_key = 'clubworx_schedule_' . $first;
        $debug_info = array(
            'clubworx_configured' => $clubworx_configured,
            'accounts' => $slugs,
            'cache_status' => get_transient($cache_key) !== false ? 'cached' : 'not_cached',
            'data_source' => $clubworx_configured ? 'clubworx_or_fallback' : 'fallback_only',
        );

        return new WP_REST_Response(array(
            'success' => true,
            'schedule' => $schedule,
            'debug' => $debug_info,
        ), 200);
    }
    
    /**
     * Create prospect in ClubWorx
     */
    public function create_prospect($request) {
        $data = $request->get_json_params();
        if (!is_array($data)) {
            $data = array();
        }

        $resolved = Clubworx_Locations::resolve_from_request($request);
        if (is_wp_error($resolved)) {
            return $this->rest_error_from_wp_error($resolved);
        }

        $slug = isset($resolved['_slug']) ? sanitize_key($resolved['_slug']) : Clubworx_Locations::get_default_slug();
        $clubworx_api_url = $resolved['api_url'];
        $clubworx_api_key = $resolved['api_key'];

        $base_url = rtrim($clubworx_api_url, '/');
        if (!str_ends_with($base_url, '/api/v2')) {
            $base_url .= '/api/v2';
        }
        $api_url = $base_url . '/prospects?account_key=' . urlencode($clubworx_api_key);

        $form_data = array(
            'first_name' => isset($data['first_name']) ? $data['first_name'] : '',
            'last_name' => isset($data['last_name']) ? $data['last_name'] : '',
            'email' => isset($data['email']) ? $data['email'] : '',
            'phone' => isset($data['phone']) ? $data['phone'] : '',
            'status' => isset($data['status']) ? $data['status'] : 'Initial Contact',
        );

        $form_string = http_build_query($form_data);

        error_log('Clubworx Integration: Prospects API URL: ' . $api_url);
        error_log('Clubworx Integration: Form data: ' . $form_string);

        $response = wp_remote_post($api_url, array(
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json',
            ),
            'body' => $form_string,
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            return new WP_REST_Response(array(
                'error' => 'ClubWorx API error',
                'message' => $response->get_error_message(),
            ), 500);
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        error_log('Clubworx Integration: ClubWorx prospects response: ' . json_encode($body));
        error_log('Clubworx Integration: Response code: ' . wp_remote_retrieve_response_code($response));

        $this->store_booking_data('prospect', $data, $body, $slug);

        $loc = $resolved;
        unset($loc['_slug']);
        if (!empty($loc['email']['enabled'])) {
            Clubworx_SMTP_Context::with_location($loc, function () use ($data, $body, $loc) {
                $this->send_prospect_notification($data, $body, $loc);
            });
        }

        return new WP_REST_Response($body, wp_remote_retrieve_response_code($response));
    }
    
    /**
     * Test ClubWorx response structure
     */
    public function test_clubworx_response($request) {
        $resolved = Clubworx_Locations::resolve_from_request($request);
        if (is_wp_error($resolved)) {
            return new WP_REST_Response(array(
                'error' => $resolved->get_error_code(),
                'message' => $resolved->get_error_message(),
            ), 500);
        }

        $clubworx_api_url = $resolved['api_url'];
        $clubworx_api_key = $resolved['api_key'];

        $base_url = rtrim($clubworx_api_url, '/');
        if (!str_ends_with($base_url, '/api/v2')) {
            $base_url .= '/api/v2';
        }

        // Test with a simple prospect creation to see response structure
        $test_data = array(
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'phone' => '0412345678',
            'source' => 'API Test',
        );

        $api_url = $base_url . '/prospects?account_key=' . urlencode($clubworx_api_key);
        $response = wp_remote_post($api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ),
            'body' => json_encode($test_data),
            'timeout' => 30,
        ));
        
        if (is_wp_error($response)) {
            return new WP_REST_Response(array(
                'error' => 'ClubWorx API error',
                'message' => $response->get_error_message()
            ), 500);
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        return new WP_REST_Response(array(
            'status_code' => wp_remote_retrieve_response_code($response),
            'response_structure' => $body,
            'response_keys' => is_array($body) ? array_keys($body) : 'Not an array',
            'api_url_used' => $api_url
        ), 200);
    }
    
    /**
     * Find available events
     */
    public function find_events($request) {
        $data = $request->get_json_params();
        if (!is_array($data)) {
            $data = array();
        }

        $resolved = Clubworx_Locations::resolve_from_request($request);
        if (is_wp_error($resolved)) {
            return $this->rest_error_from_wp_error($resolved);
        }

        $clubworx_api_url = $resolved['api_url'];
        $clubworx_api_key = $resolved['api_key'];

        $selectedClass = isset($data['selectedClass']) ? $data['selectedClass'] : '';
        $day = isset($data['day']) ? $data['day'] : '';
        
        if (empty($selectedClass) || empty($day)) {
            return new WP_REST_Response(array(
                'error' => 'Class and day information required'
            ), 400);
        }
        
        // Extract time from the selected class
        $time = $this->extract_time_from_class($selectedClass);
        $className = $this->extract_name_from_class($selectedClass);
        
        // Fetch events from ClubWorx API to get the actual event_id
        // Include past few days to catch events that might have already started
        $start_date = date('Y-m-d', strtotime('-3 days'));
        $end_date = date('Y-m-d', strtotime('+7 days'));
        
        // Ensure the base URL ends with /api/v2/ to avoid double paths
        $base_url = rtrim($clubworx_api_url, '/');
        if (!str_ends_with($base_url, '/api/v2')) {
            $base_url .= '/api/v2';
        }
        $api_url = $base_url . '/events?account_key=' . urlencode($clubworx_api_key);
        $api_url .= '&event_starts_after=' . urlencode($start_date . 'T00:00:00Z');
        $api_url .= '&event_ends_before=' . urlencode($end_date . 'T23:59:59Z');
        
        // Debug logging
        error_log('Clubworx Integration: Events API URL: ' . $api_url);
        error_log('Clubworx Integration: Looking for class: ' . $selectedClass . ' on day: ' . $day);
        
        $response = wp_remote_get($api_url, array(
            'headers' => array(
                'Accept' => 'application/json',
            ),
            'timeout' => 30,
        ));
        
        if (is_wp_error($response)) {
            error_log('Clubworx Integration: Events API error: ' . $response->get_error_message());
            return $this->generate_fallback_event($selectedClass, $day, $time, $className);
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code !== 200) {
            error_log('Clubworx Integration: Events API returned: ' . $response_code . ' - ' . json_encode($body));
            return $this->generate_fallback_event($selectedClass, $day, $time, $className);
        }
        
        // Find the matching event by class name and day
        $matchingEvent = null;
        $dayName = strtolower($day);
        
        // ClubWorx API returns events in a direct array, not nested under 'events'
        $events = is_array($body) ? $body : array();
        
        // Debug: Show all Monday events
        error_log('Clubworx Integration: Total events found: ' . count($events));
        foreach ($events as $event) {
            if (isset($event['event_start_at'])) {
                $eventDate = new DateTime($event['event_start_at']);
                $eventDay = strtolower($eventDate->format('l'));
                if ($eventDay === 'monday') {
                    error_log('Clubworx Integration: Monday event found - ' . $event['event_name'] . ' at ' . $eventDate->format('H:i'));
                }
            }
        }
        
        foreach ($events as $event) {
            if (!isset($event['event_start_at']) || !isset($event['event_name'])) {
                continue;
            }
            
            // Parse the event date with timezone (ClubWorx uses AEST +11:00)
            $eventDate = new DateTime($event['event_start_at']);
            $eventDate->setTimezone(new DateTimeZone('Australia/Sydney')); // Set to AEST
            $eventDay = strtolower($eventDate->format('l')); // Get day name like 'monday'
            $eventTime = $eventDate->format('H:i'); // Get time like '19:00'
            
            // Convert our target time to 24-hour format for comparison
            $targetTime = date('H:i', strtotime($time));
            
            // Check if event name contains our class name and matches the day and time
            $eventNameMatches = strpos(strtolower($event['event_name']), strtolower($className)) !== false;
            $dayMatches = $eventDay === $dayName;
            $timeMatches = $eventTime === $targetTime;
            
            // Debug logging for each event checked
            error_log('Clubworx Integration: Checking event - Name: ' . $event['event_name'] . ', Day: ' . $eventDay . ', Time: ' . $eventTime . ' | Target: ' . $className . ', ' . $dayName . ', ' . $targetTime);
            error_log('Clubworx Integration: Matches - Name: ' . ($eventNameMatches ? 'YES' : 'NO') . ', Day: ' . ($dayMatches ? 'YES' : 'NO') . ', Time: ' . ($timeMatches ? 'YES' : 'NO'));
            
            if ($eventNameMatches && $dayMatches && $timeMatches) {
                $matchingEvent = $event;
                error_log('Clubworx Integration: Found exact match - Event: ' . $event['event_name'] . ', Day: ' . $eventDay . ', Time: ' . $eventTime);
                break;
            }
        }
        
        if (!$matchingEvent) {
            error_log('Clubworx Integration: No matching event found, using fallback');
            return $this->generate_fallback_event($selectedClass, $day, $time, $className);
        }
        
        // Return the event data with proper structure
        $eventData = array(
            'event_id' => $matchingEvent['event_id'],
            'event_name' => $matchingEvent['event_name'],
            'event_start_at' => $matchingEvent['event_start_at'],
            'event_end_at' => $matchingEvent['event_end_at'],
            'class_name' => $className,
            'day' => $day,
            'time' => $time
        );
        
        error_log('Clubworx Integration: Found matching event: ' . json_encode($eventData));
        
        return new WP_REST_Response(array(
            'success' => true,
            'event' => $eventData
        ), 200);
    }
    
    /**
     * Generate fallback event data when ClubWorx API is unavailable
     */
    private function generate_fallback_event($selectedClass, $day, $time, $className) {
        // Generate a mock event_id based on the class information
        $mockEventId = 'fallback_' . md5($selectedClass . $day . $time);
        
        // Calculate next occurrence of the class
        $dayMap = array(
            'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4,
            'friday' => 5, 'saturday' => 6, 'sunday' => 0
        );
        
        $targetDay = isset($dayMap[strtolower($day)]) ? $dayMap[strtolower($day)] : 1;
        $today = date('w'); // 0 = Sunday, 1 = Monday, etc.
        
        $daysUntilTarget = ($targetDay - $today + 7) % 7;
        if ($daysUntilTarget === 0 && date('H:i') > $time) {
            $daysUntilTarget = 7; // If it's the same day but time has passed, get next week
        }
        
        // Set timezone to AEST for fallback events
        $aestTimezone = new DateTimeZone('Australia/Sydney');
        $now = new DateTime('now', $aestTimezone);
        $nextClassDate = $now->add(new DateInterval("P{$daysUntilTarget}D"))->format('Y-m-d');
        $nextClassDateTime = $nextClassDate . 'T' . date('H:i:s', strtotime($time));
        
        // Return the event data with proper structure
        $eventData = array(
            'event_id' => $mockEventId,
            'event_name' => $className,
            'event_start_at' => $nextClassDateTime,
            'event_end_at' => date('Y-m-d\TH:i:s', strtotime($nextClassDateTime . ' +1 hour')),
            'class_name' => $className,
            'day' => $day,
            'time' => $time
        );
        
        error_log('Clubworx Integration: Generated fallback event for class: ' . $selectedClass);
        
        return new WP_REST_Response(array(
            'success' => true,
            'event' => $eventData
        ), 200);
    }
    
    /**
     * Extract time from class string
     */
    private function extract_time_from_class($classString) {
        if (preg_match('/(\d{1,2}:\d{2} [AP]M)$/', $classString, $matches)) {
            return $matches[1];
        }
        return '';
    }
    
    /**
     * Extract name from class string
     */
    private function extract_name_from_class($classString) {
        if (preg_match('/^(.+) - \d{1,2}:\d{2} [AP]M$/', $classString, $matches)) {
            return $matches[1];
        }
        return $classString;
    }
    
    /**
     * Create booking in ClubWorx
     */
    public function create_booking($request) {
        $data = $request->get_json_params();
        if (!is_array($data)) {
            $data = array();
        }

        $resolved = Clubworx_Locations::resolve_from_request($request);
        if (is_wp_error($resolved)) {
            return $this->rest_error_from_wp_error($resolved);
        }

        $slug = isset($resolved['_slug']) ? sanitize_key($resolved['_slug']) : Clubworx_Locations::get_default_slug();
        $clubworx_api_url = $resolved['api_url'];
        $clubworx_api_key = $resolved['api_key'];

        $base_url = rtrim($clubworx_api_url, '/');
        if (!str_ends_with($base_url, '/api/v2')) {
            $base_url .= '/api/v2';
        }
        $api_url = $base_url . '/bookings?account_key=' . urlencode($clubworx_api_key);

        $form_data = array(
            'contact_key' => isset($data['contact_key']) ? $data['contact_key'] : '',
            'event_id' => isset($data['event_id']) ? $data['event_id'] : '',
        );

        $form_string = http_build_query($form_data);

        error_log('Clubworx Integration: Creating booking with data: ' . json_encode($data));
        error_log('Clubworx Integration: Form data: ' . $form_string);
        error_log('Clubworx Integration: API URL: ' . $api_url);

        $response = wp_remote_post($api_url, array(
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json',
            ),
            'body' => $form_string,
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            return new WP_REST_Response(array(
                'error' => $response->get_error_message(),
            ), 500);
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $response_code = wp_remote_retrieve_response_code($response);

        error_log('Clubworx Integration: ClubWorx bookings response: ' . json_encode($body));
        error_log('Clubworx Integration: Response code: ' . $response_code);

        $this->store_booking_data('booking', $data, $body, $slug);

        $loc = $resolved;
        unset($loc['_slug']);

        if ($response_code !== 200) {
            $mockResponse = array(
                'success' => true,
                'booking_id' => 'mock_booking_' . time(),
                'message' => 'Booking stored locally. ClubWorx API endpoint needs configuration.',
                'clubworx_error' => $body,
            );

            error_log('Clubworx Integration: ClubWorx API failed, returning mock response: ' . json_encode($mockResponse));

            if (!empty($loc['email']['enabled'])) {
                Clubworx_SMTP_Context::with_location($loc, function () use ($data, $mockResponse, $loc) {
                    $this->send_booking_notification($data, $mockResponse, $loc);
                });
            }

            return new WP_REST_Response($mockResponse, 200);
        }

        if (!empty($loc['email']['enabled'])) {
            Clubworx_SMTP_Context::with_location($loc, function () use ($data, $body, $loc) {
                $this->send_booking_notification($data, $body, $loc);
            });
        }

        return new WP_REST_Response($body, $response_code);
    }
    
    /**
     * Track attribution data
     */
    public function track_attribution($request) {
        $data = $request->get_json_params();
        
        // Store attribution data in WordPress database
        $this->store_attribution_data($data);
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Attribution tracked'
        ), 200);
    }
    
    /**
     * Send GA4 Measurement Protocol event
     */
    public function send_ga4_measurement($request) {
        $data = $request->get_json_params();
        if (!is_array($data)) {
            $data = array();
        }

        $slug = Clubworx_Locations::get_account_param_from_request($request);
        if ($slug === null) {
            $slug = Clubworx_Locations::get_default_slug();
        }
        $loc = Clubworx_Locations::get($slug);
        if ($loc === null) {
            return new WP_REST_Response(array(
                'success' => false,
                'reason' => 'Unknown Clubworx location',
            ), 200);
        }

        $a = isset($loc['analytics']) && is_array($loc['analytics']) ? $loc['analytics'] : array();
        $ga4_api_secret = isset($a['ga4_api_secret']) ? $a['ga4_api_secret'] : '';
        $ga4_measurement_id = isset($a['ga4_measurement_id']) ? $a['ga4_measurement_id'] : '';

        if ($ga4_api_secret === '' || $ga4_measurement_id === '') {
            return new WP_REST_Response(array(
                'success' => false,
                'reason' => 'GA4 Measurement Protocol not configured',
                'fallback' => 'Configure GA4 Measurement ID and API secret for this location',
            ), 200);
        }

        $measurement_url = "https://www.google-analytics.com/mp/collect?measurement_id={$ga4_measurement_id}&api_secret={$ga4_api_secret}";

        $response = wp_remote_post($measurement_url, array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => wp_json_encode($data),
            'timeout' => 10,
        ));

        if (is_wp_error($response)) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => $response->get_error_message(),
            ), 500);
        }

        $event_count = isset($data['events']) && is_array($data['events']) ? count($data['events']) : 0;

        return new WP_REST_Response(array(
            'success' => true,
            'measurement_id' => $ga4_measurement_id,
            'events_sent' => $event_count,
        ), 200);
    }
    
    /**
     * Store booking data in WordPress database
     */
    private function store_booking_data($type, $request_data, $response_data, $account = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'clubworx_bookings';

        if ($account === null || $account === '') {
            if (is_array($request_data) && !empty($request_data['account'])) {
                $account = sanitize_key($request_data['account']);
            } else {
                $account = Clubworx_Locations::get_default_slug();
            }
        } else {
            $account = sanitize_key($account);
        }

        // Create table if it doesn't exist
        $this->create_bookings_table();
        
        // Extract source and medium from request data
        $source = isset($request_data['source']) ? $request_data['source'] : null;
        $medium = isset($request_data['medium']) ? $request_data['medium'] : null;
        
        // For booking records, try to get personal info from the current session or form data
        $enhanced_request_data = $request_data;
        
        // If this is a booking and we have a contact_key, try to get the prospect data
        if ($type === 'booking' && isset($request_data['contact_key'])) {
            // Look for the most recent prospect record with this contact_key
            // The contact_key is in the response_data from ClubWorx, not request_data
            $prospect_record = $wpdb->get_row($wpdb->prepare(
                "SELECT request_data, source, medium FROM $table_name WHERE type = 'prospect' AND JSON_EXTRACT(response_data, '$.contact_key') = %s ORDER BY created_at DESC LIMIT 1",
                $request_data['contact_key']
            ));
            
            if ($prospect_record) {
                $prospect_data = json_decode($prospect_record->request_data, true);
                // Merge prospect data with booking data
                $enhanced_request_data = array_merge($prospect_data, $request_data);
                
                // If booking doesn't have source/medium, use prospect's source/medium
                if (empty($source) && !empty($prospect_record->source)) {
                    $source = $prospect_record->source;
                }
                if (empty($medium) && !empty($prospect_record->medium)) {
                    $medium = $prospect_record->medium;
                }
                
                error_log('Clubworx Integration: Enhanced booking data with prospect info: ' . json_encode($enhanced_request_data));
            } else {
                error_log('Clubworx Integration: No prospect record found for contact_key: ' . $request_data['contact_key']);
            }
        }
        
        $wpdb->insert($table_name, array(
            'type' => $type,
            'account' => $account,
            'request_data' => json_encode($enhanced_request_data),
            'response_data' => json_encode($response_data),
            'source' => $source,
            'medium' => $medium,
            'created_at' => current_time('mysql'),
        ));
    }
    
    /**
     * Store attribution data in WordPress database
     */
    private function store_attribution_data($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'clubworx_attribution';
        
        // Create table if it doesn't exist
        $this->create_attribution_table();
        
        $wpdb->insert($table_name, array(
            'contact_key' => isset($data['contact_key']) ? $data['contact_key'] : '',
            'utm_source' => isset($data['utm_source']) ? $data['utm_source'] : '',
            'utm_medium' => isset($data['utm_medium']) ? $data['utm_medium'] : '',
            'utm_campaign' => isset($data['utm_campaign']) ? $data['utm_campaign'] : '',
            'referrer' => isset($data['referrer']) ? $data['referrer'] : '',
            'landing_page' => isset($data['landing_page']) ? $data['landing_page'] : '',
            'program_interest' => isset($data['program_interest']) ? $data['program_interest'] : '',
            'data' => json_encode($data),
            'created_at' => current_time('mysql'),
        ));
    }
    
    /**
     * Create bookings table
     */
    private function create_bookings_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'clubworx_bookings';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            type varchar(50) NOT NULL,
            request_data longtext,
            response_data longtext,
            source varchar(255) DEFAULT NULL,
            medium varchar(255) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY type (type),
            KEY created_at (created_at),
            KEY source (source),
            KEY medium (medium)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Add new columns to existing tables if they don't exist
        $this->update_bookings_table_structure();
    }
    
    /**
     * Update bookings table structure to add source and medium columns
     */
    private function update_bookings_table_structure() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'clubworx_bookings';
        
        // Check if source column exists
        $source_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'source'");
        if (empty($source_exists)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN source varchar(255) DEFAULT NULL");
            $wpdb->query("ALTER TABLE $table_name ADD KEY source (source)");
        }
        
        // Check if medium column exists
        $medium_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'medium'");
        if (empty($medium_exists)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN medium varchar(255) DEFAULT NULL");
            $wpdb->query("ALTER TABLE $table_name ADD KEY medium (medium)");
        }
    }
    
    /**
     * Create attribution table
     */
    private function create_attribution_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'clubworx_attribution';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            contact_key varchar(255),
            utm_source varchar(255),
            utm_medium varchar(255),
            utm_campaign varchar(255),
            referrer text,
            landing_page text,
            program_interest varchar(100),
            data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY contact_key (contact_key),
            KEY utm_source (utm_source),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Get diagnostic information
     */
    public function get_diagnostics($request) {
        $locations_diag = array();
        foreach (Clubworx_Locations::all() as $slug => $loc) {
            $configured = !empty($loc['api_url']) && !empty($loc['api_key']);
            $cache_key = 'clubworx_schedule_' . sanitize_key($slug);
            $locations_diag[$slug] = array(
                'label' => isset($loc['label']) ? $loc['label'] : $slug,
                'api_url' => isset($loc['api_url']) ? $loc['api_url'] : '',
                'clubworx_configured' => $configured,
                'schedule_cache' => get_transient($cache_key) !== false ? 'cached' : 'not_cached',
            );
        }

        $def = Clubworx_Locations::get_default_slug();
        try {
            $schedule = $this->get_clubworx_schedule($def);
            $sample = array();
            if (isset($schedule['adults']['general']['monday']) && is_array($schedule['adults']['general']['monday'])) {
                $sample = $schedule['adults']['general']['monday'];
            }
            $schedule_summary = array(
                'default_location' => $def,
                'has_kids' => isset($schedule['kids']),
                'has_adults' => isset($schedule['adults']),
                'has_women' => isset($schedule['women']),
                'sample_classes' => $sample,
            );
        } catch (Exception $e) {
            $schedule_summary = array('error' => $e->getMessage());
        }

        $diagnostics = array(
            'timestamp' => current_time('mysql'),
            'wordpress_version' => get_bloginfo('version'),
            'plugin_version' => CLUBWORX_INTEGRATION_VERSION,
            'locations' => $locations_diag,
            'current_schedule' => $schedule_summary,
            'rest_api_url' => rest_url('clubworx/v1/'),
            'wp_rest_enabled' => rest_url() !== false,
        );

        return new WP_REST_Response($diagnostics, 200);
    }
    
    /**
     * Test ClubWorx API and return raw response
     */
    public function test_clubworx_raw($request) {
        $resolved = Clubworx_Locations::resolve_from_request($request);
        if (is_wp_error($resolved)) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => $resolved->get_error_code(),
                'message' => $resolved->get_error_message(),
            ), 400);
        }

        $clubworx_api_url = $resolved['api_url'];
        $clubworx_api_key = $resolved['api_key'];

        try {
            // Build the correct ClubWorx API URL with required parameters
            $base_url = rtrim($clubworx_api_url, '/');
            if (strpos($base_url, '/api/v2') === false) {
                $base_url = $base_url . '/api/v2';
            }
            
            // Set date range for events (current week only)
            $start_date = date('Y-m-d', strtotime('monday this week'));
            $end_date = date('Y-m-d', strtotime('monday next week')); // Monday of next week (exclusive)
            
            // Build query parameters as per ClubWorx API docs
            $query_params = array(
                'account_key' => $clubworx_api_key,
                'event_starts_after' => $start_date,
                'event_ends_before' => $end_date,
                'page' => 1,
                'page_size' => 100
            );
            
            $api_url = $base_url . '/events?' . http_build_query($query_params);
            
            error_log('Clubworx Integration: Testing ClubWorx API with correct format: ' . $api_url);
            
            // ClubWorx uses query parameters, not headers for authentication
            $headers = array(
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            );
            
            $response = wp_remote_get($api_url, array(
                'headers' => $headers,
                'timeout' => 15,
            ));
            
            if (is_wp_error($response)) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'error' => 'API request failed',
                    'message' => $response->get_error_message(),
                    'api_url' => $api_url
                ), 500);
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            error_log('Clubworx Integration: ClubWorx API response status: ' . $status_code);
            
            return new WP_REST_Response(array(
                'success' => $status_code === 200,
                'status_code' => $status_code,
                'api_url' => $api_url,
                'auth_method' => 'query_parameters',
                'date_range' => $start_date . ' to ' . $end_date,
                'raw_response' => $body,
                'parsed_response' => json_decode($body, true),
                'response_headers' => wp_remote_retrieve_headers($response)->getAll(),
                'timestamp' => current_time('mysql')
            ), 200);
            
        } catch (Exception $e) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Exception occurred',
                'message' => $e->getMessage(),
                'api_url' => $api_url
            ), 500);
        }
    }
    
    /**
     * Debug class processing to show exactly what's happening
     */
    public function debug_class_processing($request) {
        $resolved = Clubworx_Locations::resolve_from_request($request);
        if (is_wp_error($resolved)) {
            return new WP_REST_Response(array(
                'configured' => false,
                'error' => $resolved->get_error_message(),
                'raw_clubworx_data' => null,
                'processed_schedule' => null,
                'processing_stats' => array(
                    'total_raw_classes' => 0,
                    'total_processed_classes' => 0,
                    'total_skipped_classes' => 0,
                    'class_breakdown' => array(),
                ),
            ), 200);
        }

        $clubworx_api_url = $resolved['api_url'];
        $clubworx_api_key = $resolved['api_key'];
        $clubworx_configured = true;

        $response = array(
            'configured' => $clubworx_configured,
            'raw_clubworx_data' => null,
            'processed_schedule' => null,
            'processing_stats' => array(
                'total_raw_classes' => 0,
                'total_processed_classes' => 0,
                'total_skipped_classes' => 0,
                'class_breakdown' => array(),
            ),
        );

        if ($clubworx_configured) {
            
            $raw_data = null;
            try {
                // Build the correct ClubWorx API URL with required parameters
                $base_url = rtrim($clubworx_api_url, '/');
                if (strpos($base_url, '/api/v2') === false) {
                    $base_url = $base_url . '/api/v2';
                }
                
                // Set date range for events (current week only)
                $start_date = date('Y-m-d', strtotime('monday this week'));
                $end_date = date('Y-m-d', strtotime('monday next week')); // Monday of next week (exclusive)
                
                // Build query parameters as per ClubWorx API docs
                $query_params = array(
                    'account_key' => $clubworx_api_key,
                    'event_starts_after' => $start_date,
                    'event_ends_before' => $end_date,
                    'page' => 1,
                    'page_size' => 100
                );
                
                $api_url = $base_url . '/events?' . http_build_query($query_params);
                
                $api_response = wp_remote_get($api_url, array(
                    'headers' => array(
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                    ),
                    'timeout' => 15,
                ));
                
                if (!is_wp_error($api_response) && wp_remote_retrieve_response_code($api_response) === 200) {
                    $body = wp_remote_retrieve_body($api_response);
                    $raw_data = json_decode($body, true);
                }
            } catch (Exception $e) {
                error_log('Clubworx Integration: Debug API call failed: ' . $e->getMessage());
            }
            
            $response['raw_clubworx_data'] = $raw_data;
            
            // Count raw classes
            if (is_array($raw_data)) {
                // ClubWorx returns direct array of events
                $response['processing_stats']['total_raw_classes'] = count($raw_data);
            } elseif (isset($raw_data['events'])) {
                $response['processing_stats']['total_raw_classes'] = count($raw_data['events']);
            } elseif (isset($raw_data['classes'])) {
                $response['processing_stats']['total_raw_classes'] = count($raw_data['classes']);
            } elseif (isset($raw_data['schedule'])) {
                $response['processing_stats']['total_raw_classes'] = count($raw_data['schedule']);
            }
            
            // Get processed schedule
            $sched_slug = isset($resolved['_slug']) ? sanitize_key($resolved['_slug']) : Clubworx_Locations::get_default_slug();
            $processed = $this->get_clubworx_schedule($sched_slug);
            $response['processed_schedule'] = $processed;
            
            // Count processed classes
            $total_processed = 0;
            $breakdown = array();
            
            foreach ($processed as $category => $subcats) {
                if (is_array($subcats)) {
                    foreach ($subcats as $subcat => $days) {
                        if (is_array($days)) {
                            foreach ($days as $day => $classes) {
                                if (is_array($classes)) {
                                    $count = count($classes);
                                    $total_processed += $count;
                                    $breakdown[$category . '_' . $subcat . '_' . $day] = $count;
                                }
                            }
                        }
                    }
                } elseif (is_array($subcats)) {
                    // Handle women category directly
                    foreach ($subcats as $day => $classes) {
                        if (is_array($classes)) {
                            $count = count($classes);
                            $total_processed += $count;
                            $breakdown[$category . '_' . $day] = $count;
                        }
                    }
                }
            }
            
            $response['processing_stats']['total_processed_classes'] = $total_processed;
            $response['processing_stats']['total_skipped_classes'] = 
                $response['processing_stats']['total_raw_classes'] - $total_processed;
            $response['processing_stats']['class_breakdown'] = $breakdown;
        }
        
        return new WP_REST_Response($response, 200);
    }
    
    /**
     * Clear schedule cache
     */
    public function clear_schedule_cache($request) {
        delete_transient('clubworx_schedule');
        foreach (array_keys(Clubworx_Locations::all()) as $slug) {
            delete_transient('clubworx_schedule_' . sanitize_key($slug));
        }

        $def = Clubworx_Locations::get_default_slug();
        $loc = Clubworx_Locations::get($def);
        $clubworx_configured = $loc && !empty($loc['api_url']) && !empty($loc['api_key']);

        error_log('Clubworx Integration: Schedule cache cleared by admin (all locations)');

        $message = 'Schedule cache cleared successfully. ';
        if (!$clubworx_configured) {
            $message .= 'Note: Configure API URL and key for each location under Settings.';
        } else {
            $message .= 'Next request will fetch fresh data from ClubWorx.';
        }

        return new WP_REST_Response(array(
            'success' => true,
            'message' => $message,
            'clubworx_configured' => $clubworx_configured,
            'api_url' => ($clubworx_configured && $loc) ? $loc['api_url'] : 'not_set',
        ), 200);
    }
    
    /**
     * Get ClubWorx schedule for one location slug (cached per slug).
     *
     * @param string|null $slug Location slug; default location when null.
     * @return array<string,mixed>
     */
    private function get_clubworx_schedule($slug = null) {
        if ($slug === null || $slug === '') {
            $slug = Clubworx_Locations::get_default_slug();
        }
        $slug = sanitize_key($slug);
        $loc = Clubworx_Locations::get($slug);
        if ($loc === null) {
            return $this->get_fallback_schedule_for_location(null);
        }

        $clubworx_api_url = isset($loc['api_url']) ? $loc['api_url'] : '';
        $clubworx_api_key = isset($loc['api_key']) ? $loc['api_key'] : '';

        if (empty($clubworx_api_url) || empty($clubworx_api_key)) {
            error_log('Clubworx Integration: ClubWorx API not configured for location ' . $slug . ', using fallback schedule');
            return $this->get_fallback_schedule_for_location($loc);
        }

        $cache_key = 'clubworx_schedule_' . $slug;
        $cached_schedule = get_transient($cache_key);

        if ($cached_schedule !== false) {
            error_log('Clubworx Integration: Using cached schedule data for ' . $slug);
            return $cached_schedule;
        }

        try {
            error_log('Clubworx Integration: Fetching schedule from ClubWorx API for ' . $slug . ': ' . $clubworx_api_url);

            $base_url = rtrim($clubworx_api_url, '/');
            if (strpos($base_url, '/api/v2') === false) {
                $base_url = $base_url . '/api/v2';
            }

            $start_date = date('Y-m-d', strtotime('monday this week'));
            $end_date = date('Y-m-d', strtotime('monday next week'));

            $query_params = array(
                'account_key' => $clubworx_api_key,
                'event_starts_after' => $start_date,
                'event_ends_before' => $end_date,
                'page' => 1,
                'page_size' => 100,
            );

            $api_url = $base_url . '/events?' . http_build_query($query_params);

            $response = wp_remote_get($api_url, array(
                'headers' => array(
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ),
                'timeout' => 15,
            ));

            if (is_wp_error($response)) {
                error_log('Clubworx Integration: ClubWorx API error: ' . $response->get_error_message());
                return $this->get_fallback_schedule_for_location($loc);
            }

            $status_code = wp_remote_retrieve_response_code($response);
            if ($status_code !== 200) {
                error_log('Clubworx Integration: ClubWorx API returned status ' . $status_code);
                return $this->get_fallback_schedule_for_location($loc);
            }

            $body = wp_remote_retrieve_body($response);
            $schedule_data = json_decode($body, true);

            if (!$schedule_data || !is_array($schedule_data)) {
                error_log('Clubworx Integration: Invalid schedule data received from ClubWorx');
                return $this->get_fallback_schedule_for_location($loc);
            }

            $formatted_schedule = $this->format_clubworx_schedule($schedule_data);

            $cache_expiration = $this->get_sunday_midnight_timestamp();
            $cache_duration = $cache_expiration - time();

            if ($cache_duration <= 60) {
                $cache_expiration = strtotime('next Sunday midnight', current_time('timestamp'));
                $cache_duration = $cache_expiration - time();
            }

            set_transient($cache_key, $formatted_schedule, $cache_duration);

            error_log('Clubworx Integration: Successfully fetched and cached schedule for ' . $slug . ' until ' . date('Y-m-d H:i:s', $cache_expiration));
            return $formatted_schedule;

        } catch (Exception $e) {
            error_log('Clubworx Integration: Exception fetching ClubWorx schedule: ' . $e->getMessage());
            return $this->get_fallback_schedule_for_location($loc);
        }
    }

    /**
     * Fallback schedule JSON from one location row.
     *
     * @param array<string,mixed>|null $loc
     * @return array<string,mixed>
     */
    private function get_fallback_schedule_for_location($loc) {
        if ($loc !== null && !empty($loc['fallback_schedule_json'])) {
            $decoded = json_decode($loc['fallback_schedule_json'], true);
            if (is_array($decoded) && !empty($decoded)) {
                return $decoded;
            }
        }

        return $this->empty_schedule_tree();
    }
    
    /**
     * Format ClubWorx schedule data into plugin format
     */
    private function format_clubworx_schedule($clubworx_data) {
        error_log('Clubworx Integration: Raw ClubWorx data received: ' . json_encode($clubworx_data));
        
        // If ClubWorx returns data in the expected format, use it directly
        if (isset($clubworx_data['kids']) && isset($clubworx_data['adults'])) {
            error_log('Clubworx Integration: Using ClubWorx data directly');
            return $clubworx_data;
        }
        
        // ClubWorx likely returns a different format - let's transform it
        error_log('Clubworx Integration: Transforming ClubWorx data format');
        
        $formatted = array(
            'kids' => array(
                'under6' => array(),
                'over6' => array()
            ),
            'adults' => array(
                'general' => array(),
                'foundations' => array()
            ),
            'women' => array()
        );
        
        $total_classes_processed = 0;
        $total_classes_skipped = 0;
        $processed_events = array(); // Track processed events to prevent duplicates
        
        // Store classes with their times for sorting
        $classes_with_times = array();
        
        // Get the events array
        $events = array();
        if (is_array($clubworx_data)) {
            // ClubWorx /api/v2/events returns array of event objects
            $events = $clubworx_data;
            error_log('Clubworx Integration: Processing ' . count($events) . ' events from ClubWorx');
        } elseif (isset($clubworx_data['events']) && is_array($clubworx_data['events'])) {
            $events = $clubworx_data['events'];
            error_log('Clubworx Integration: Processing ' . count($events) . ' events from ClubWorx (wrapped format)');
        } elseif (isset($clubworx_data['classes']) && is_array($clubworx_data['classes'])) {
            $events = $clubworx_data['classes'];
            error_log('Clubworx Integration: Processing ' . count($events) . ' classes from ClubWorx');
        } else {
            // Unknown format - log the structure and return fallback
            error_log('Clubworx Integration: Unknown ClubWorx data format. Type: ' . gettype($clubworx_data) . ', Keys: ' . (is_array($clubworx_data) ? implode(', ', array_keys($clubworx_data)) : 'N/A'));
            return $this->empty_schedule_tree();
        }
        
        // Get current week boundaries for filtering
        $current_week_start = strtotime('monday this week');
        $current_week_end = strtotime('sunday this week 23:59:59');
        // Process each event and collect valid classes
        foreach ($events as $index => $event) {
            // Filter events to only include current week
            if (isset($event['event_start_at'])) {
                $event_timestamp = strtotime($event['event_start_at']);
                if ($event_timestamp < $current_week_start || $event_timestamp > $current_week_end) {
                    error_log('Clubworx Integration: SKIPPING Class #' . $index . ' - Outside current week. Event date: ' . $event['event_start_at']);
                    $total_classes_skipped++;
                    continue;
                }
            }
            
            $classInfo = $this->parse_event_to_class_info($event, $index);
            if ($classInfo) {
                // Create a unique key based on class name, day, and time to catch recurring classes
                $duplicate_key = 'class_' . md5($classInfo['name'] . '_' . $classInfo['day'] . '_' . $classInfo['time']);
                
                if (!isset($processed_events[$duplicate_key])) {
                    $processed_events[$duplicate_key] = true;
                    $classes_with_times[] = $classInfo;
                    $total_classes_processed++;
                    error_log('Clubworx Integration: ADDING Class #' . $index . ' - Key: ' . $duplicate_key);
                } else {
                    error_log('Clubworx Integration: SKIPPING Class #' . $index . ' - DUPLICATE: ' . $duplicate_key);
                    $total_classes_skipped++;
                }
            } else {
                $total_classes_skipped++;
            }
        }
        
        // Sort classes by day first, then by time within each day
        usort($classes_with_times, function($a, $b) {
            if ($a['day'] !== $b['day']) {
                return strcmp($a['day'], $b['day']);
            }
            // Convert time to comparable format for sorting
            $timeA = $this->time_to_minutes($a['time']);
            $timeB = $this->time_to_minutes($b['time']);
            return $timeA - $timeB;
        });
        
        // Add sorted classes to formatted schedule
        foreach ($classes_with_times as $classInfo) {
            $this->add_class_to_formatted_schedule($formatted, $classInfo);
        }
        
        error_log('Clubworx Integration: Processed ' . $total_classes_processed . ' classes, skipped ' . $total_classes_skipped . ' classes');
        error_log('Clubworx Integration: Formatted schedule: ' . json_encode($formatted));
        return $formatted;
    }
    
    /**
     * Parse event data to extract class information
     */
    private function parse_event_to_class_info($event, $index = 0) {
        // Handle ClubWorx API field names from /api/v2/events
        $className = isset($event['event_name']) ? $event['event_name'] : 
                    (isset($event['name']) ? $event['name'] : 
                    (isset($event['class_name']) ? $event['class_name'] : 
                    (isset($event['title']) ? $event['title'] : '')));
        
        $time = '';
        $day = '';
        
        // Handle ClubWorx event_start_at field (ISO datetime format)
        if (isset($event['event_start_at'])) {
            $startDateTime = $event['event_start_at'];
            
            // Create DateTime object to handle timezone properly
            $dt = new DateTime($startDateTime);
            
            // Convert to day name (local timezone)
            $day = strtolower($dt->format('l'));
            
            // Convert to 12-hour format (local timezone)
            $time = $dt->format('g:i A');
            
            error_log('Clubworx Integration: Converted event_start_at ' . $startDateTime . ' to day: ' . $day . ', time: ' . $time);
        } else {
            // Fallback to other time/date fields
            $day = isset($event['day']) ? strtolower($event['day']) : 
                   (isset($event['day_of_week']) ? strtolower($event['day_of_week']) : '');
            
            $time = isset($event['time']) ? $event['time'] : 
                   (isset($event['start_time']) ? $event['start_time'] : 
                   (isset($event['event_time']) ? $event['event_time'] : ''));
            
            // Handle date-based events (convert to day of week)
            if (empty($day) && isset($event['date'])) {
                $date = $event['date'];
                $dayOfWeek = date('l', strtotime($date));
                $day = strtolower($dayOfWeek);
                error_log('Clubworx Integration: Converted date ' . $date . ' to day ' . $day);
            }
        }
        
        // Log all field values for debugging
        error_log('Clubworx Integration: Class #' . $index . ' - Name: "' . $className . '", Day: "' . $day . '", Time: "' . $time . '"');
        error_log('Clubworx Integration: Class #' . $index . ' - Raw data: ' . json_encode($event));
        
        if (empty($className)) {
            error_log('Clubworx Integration: SKIPPING Class #' . $index . ' - Missing name field. Available fields: ' . implode(', ', array_keys($event)));
            return false;
        }
        
        if (empty($day)) {
            error_log('Clubworx Integration: SKIPPING Class #' . $index . ' - Missing day field. Available fields: ' . implode(', ', array_keys($event)));
            return false;
        }
        
        // Format class name with time
        $displayName = $className;
        if (!empty($time)) {
            $displayName .= ' - ' . $time;
        }
        
        return array(
            'name' => $className,
            'displayName' => $displayName,
            'day' => $day,
            'time' => $time,
            'category' => $this->determine_class_category($className)
        );
    }
    
    /**
     * Determine class category based on name
     */
    private function determine_class_category($className) {
        $className_lower = strtolower($className);
        
        if (strpos($className_lower, 'little') !== false || 
            strpos($className_lower, 'under 6') !== false || 
            strpos($className_lower, '4-6') !== false ||
            strpos($className_lower, 'little kids') !== false) {
            return 'kids_under6';
        } elseif (strpos($className_lower, 'big kids') !== false || 
                  strpos($className_lower, '7-12') !== false || 
                  strpos($className_lower, 'over 6') !== false) {
            return 'kids_over6';
        } elseif (strpos($className_lower, 'foundations') !== false) {
            return 'adults_foundations';
        } elseif (strpos($className_lower, 'women') !== false || 
                  strpos($className_lower, 'female') !== false) {
            return 'women';
        } else {
            return 'adults_general';
        }
    }
    
    /**
     * Convert time string to minutes for sorting
     */
    private function time_to_minutes($time) {
        if (empty($time)) return 0;
        
        // Parse time like "6:00 PM" or "12:30 PM"
        if (preg_match('/(\d{1,2}):(\d{2}) (AM|PM)/', $time, $matches)) {
            $hours = (int)$matches[1];
            $minutes = (int)$matches[2];
            $ampm = $matches[3];
            
            if ($ampm === 'PM' && $hours !== 12) {
                $hours += 12;
            }
            if ($ampm === 'AM' && $hours === 12) {
                $hours = 0;
            }
            
            return $hours * 60 + $minutes;
        }
        
        return 0;
    }
    
    /**
     * Add a class to the formatted schedule
     */
    private function add_class_to_formatted_schedule(&$formatted, $classInfo) {
        $day = $classInfo['day'];
        $displayName = $classInfo['displayName'];
        $category = $classInfo['category'];
        
        switch ($category) {
            case 'kids_under6':
                $formatted['kids']['under6'][$day][] = $displayName;
                break;
            case 'kids_over6':
                $formatted['kids']['over6'][$day][] = $displayName;
                break;
            case 'adults_foundations':
                $formatted['adults']['foundations'][$day][] = $displayName;
                break;
            case 'women':
                $formatted['women'][$day][] = $displayName;
                break;
            default:
                $formatted['adults']['general'][$day][] = $displayName;
                break;
        }
        
        error_log('Clubworx Integration: ADDED Class: "' . $displayName . '" (' . $day . ') -> Category: ' . $category);
    }
    
    /**
     * Add a class to the formatted schedule based on class name and time (DEPRECATED)
     */
    private function add_class_to_schedule(&$formatted, $class, $index = 0, &$processed_events = array()) {
        // Handle ClubWorx API field names from /api/v2/events
        $className = isset($class['event_name']) ? $class['event_name'] : 
                    (isset($class['name']) ? $class['name'] : 
                    (isset($class['class_name']) ? $class['class_name'] : 
                    (isset($class['title']) ? $class['title'] : '')));
        
        $time = '';
        $day = '';
        
        // Handle ClubWorx event_start_at field (ISO datetime format)
        if (isset($class['event_start_at'])) {
            $startDateTime = $class['event_start_at'];
            
            // Create DateTime object to handle timezone properly
            $dt = new DateTime($startDateTime);
            
            // Convert to day name (local timezone)
            $day = strtolower($dt->format('l'));
            
            // Convert to 12-hour format (local timezone)
            $time = $dt->format('g:i A');
            
            error_log('Clubworx Integration: Converted event_start_at ' . $startDateTime . ' to day: ' . $day . ', time: ' . $time);
        } else {
            // Fallback to other time/date fields
            $day = isset($class['day']) ? strtolower($class['day']) : 
                   (isset($class['day_of_week']) ? strtolower($class['day_of_week']) : '');
            
            $time = isset($class['time']) ? $class['time'] : 
                   (isset($class['start_time']) ? $class['start_time'] : 
                   (isset($class['event_time']) ? $class['event_time'] : ''));
            
            // Handle date-based events (convert to day of week)
            if (empty($day) && isset($class['date'])) {
                $date = $class['date'];
                $dayOfWeek = date('l', strtotime($date));
                $day = strtolower($dayOfWeek);
                error_log('Clubworx Integration: Converted date ' . $date . ' to day ' . $day);
            }
        }
        
        // Log all field values for debugging
        error_log('Clubworx Integration: Class #' . $index . ' - Name: "' . $className . '", Day: "' . $day . '", Time: "' . $time . '"');
        error_log('Clubworx Integration: Class #' . $index . ' - Raw data: ' . json_encode($class));
        
        if (empty($className)) {
            error_log('Clubworx Integration: SKIPPING Class #' . $index . ' - Missing name field. Available fields: ' . implode(', ', array_keys($class)));
            return false;
        }
        
        if (empty($day)) {
            error_log('Clubworx Integration: SKIPPING Class #' . $index . ' - Missing day field. Available fields: ' . implode(', ', array_keys($class)));
            return false;
        }
        
        // Check for duplicates using event_id or combination of name, day, time
        $duplicate_key = '';
        if (isset($class['event_id'])) {
            $duplicate_key = 'event_' . $class['event_id'];
        } else {
            $duplicate_key = 'class_' . md5($className . '_' . $day . '_' . $time);
        }
        
        if (isset($processed_events[$duplicate_key])) {
            error_log('Clubworx Integration: SKIPPING Class #' . $index . ' - DUPLICATE: ' . $duplicate_key);
            return false;
        }
        
        $processed_events[$duplicate_key] = true;
        
        // Format class name with time - use original time format if available
        $displayName = $className;
        if (!empty($time)) {
            // For now, use the original time format from ClubWorx
            $displayName .= ' - ' . $time;
        }
        
        // Categorize classes based on name patterns
        $className_lower = strtolower($className);
        $category = 'unknown';
        
        if (strpos($className_lower, 'little') !== false || 
            strpos($className_lower, 'under 6') !== false || 
            strpos($className_lower, '4-6') !== false ||
            strpos($className_lower, 'little kids') !== false) {
            $formatted['kids']['under6'][$day][] = $displayName;
            $category = 'kids_under6';
        } elseif (strpos($className_lower, 'big kids') !== false || 
                  strpos($className_lower, '7-12') !== false || 
                  strpos($className_lower, 'over 6') !== false) {
            $formatted['kids']['over6'][$day][] = $displayName;
            $category = 'kids_over6';
        } elseif (strpos($className_lower, 'foundations') !== false) {
            $formatted['adults']['foundations'][$day][] = $displayName;
            $category = 'adults_foundations';
        } elseif (strpos($className_lower, 'women') !== false || 
                  strpos($className_lower, 'female') !== false) {
            $formatted['women'][$day][] = $displayName;
            $category = 'women';
        } else {
            // Default to general adults class
            $formatted['adults']['general'][$day][] = $displayName;
            $category = 'adults_general';
        }
        
        error_log('Clubworx Integration: ADDED Class #' . $index . ': "' . $displayName . '" (' . $day . ') -> Category: ' . $category);
        return true;
    }
    
    /**
     * Format time from various formats to display format
     */
    private function format_time($time) {
        if (empty($time)) {
            return '';
        }
        
        // If already in 12-hour format, return as is
        if (strpos($time, ':') !== false && (strpos($time, 'am') !== false || strpos($time, 'pm') !== false)) {
            return $time;
        }
        
        // Convert 24-hour format to 12-hour
        if (strpos($time, ':') !== false && strlen($time) <= 5) {
            $timeObj = DateTime::createFromFormat('H:i', $time);
            if ($timeObj) {
                return $timeObj->format('g:i A');
            }
        }
        
        // Return original if can't parse
        return $time;
    }
    
    /**
     * Get timestamp for next Sunday midnight (end of current week)
     * Cache expires at midnight on Sunday to refresh for the new week
     * 
     * @return int Unix timestamp for next Sunday at 00:00:00
     */
    private function get_sunday_midnight_timestamp() {
        $now = current_time('timestamp');
        $current_day = (int) date('w', $now); // 0 = Sunday, 1 = Monday, etc.
        
        // Calculate days until next Sunday midnight
        if ($current_day === 0) {
            // Today is Sunday - expire at midnight tonight (end of week)
            $today_midnight = strtotime('midnight', $now);
            // If we're very close to or past midnight, use next Sunday
            if ($now >= $today_midnight) {
                return strtotime('+7 days', $today_midnight); // Next Sunday
            }
            return $today_midnight; // Tonight's midnight
        } else {
            // Not Sunday - get the coming Sunday midnight
            $days_until_sunday = 7 - $current_day;
            $next_sunday = strtotime("+{$days_until_sunday} days", $now);
            return strtotime('midnight', $next_sunday);
        }
    }
    
    /**
     * Format booking notification email as HTML
     */
    private function format_booking_email_html($booking_data, $response_data) {
        // Extract data with fallbacks
        $contact_key = isset($booking_data['contact_key']) ? esc_html($booking_data['contact_key']) : 'N/A';
        $event_id = isset($booking_data['event_id']) ? esc_html($booking_data['event_id']) : 'N/A';
        
        // Get personal information
        $first_name = isset($booking_data['first_name']) ? esc_html($booking_data['first_name']) : 
                     (isset($booking_data['personal']['firstName']) ? esc_html($booking_data['personal']['firstName']) : 'N/A');
        $last_name = isset($booking_data['last_name']) ? esc_html($booking_data['last_name']) : 
                    (isset($booking_data['personal']['lastName']) ? esc_html($booking_data['personal']['lastName']) : 'N/A');
        $email = isset($booking_data['email']) ? esc_html($booking_data['email']) : 
                (isset($booking_data['personal']['email']) ? esc_html($booking_data['personal']['email']) : 'N/A');
        $phone = isset($booking_data['phone']) ? esc_html($booking_data['phone']) : 
                (isset($booking_data['personal']['phone']) ? esc_html($booking_data['personal']['phone']) : 'N/A');
        
        // Get program information with proper fallbacks
        $group = '';
        if (isset($booking_data['group']) && !empty($booking_data['group'])) {
            $group = esc_html($booking_data['group']);
        } elseif (isset($booking_data['program']['group']) && !empty($booking_data['program']['group'])) {
            $group = esc_html($booking_data['program']['group']);
        } elseif (isset($booking_data['programInfo']['interestedIn']) && !empty($booking_data['programInfo']['interestedIn'])) {
            $group = esc_html($booking_data['programInfo']['interestedIn']);
        }
        $group = $group ?: 'N/A';
        
        $age_group = '';
        if (isset($booking_data['ageGroup']) && !empty($booking_data['ageGroup'])) {
            $age_group = esc_html($booking_data['ageGroup']);
        } elseif (isset($booking_data['program']['ageGroup']) && !empty($booking_data['program']['ageGroup'])) {
            $age_group = esc_html($booking_data['program']['ageGroup']);
        }
        $age_group = $age_group ?: 'N/A';
        
        $day = '';
        if (isset($booking_data['day']) && !empty($booking_data['day'])) {
            $day = esc_html($booking_data['day']);
        } elseif (isset($booking_data['program']['day']) && !empty($booking_data['program']['day'])) {
            $day = esc_html($booking_data['program']['day']);
        }
        $day = $day ?: 'N/A';
        
        $selected_class = '';
        if (isset($booking_data['selectedClass']) && !empty($booking_data['selectedClass'])) {
            $selected_class = esc_html($booking_data['selectedClass']);
        } elseif (isset($booking_data['program']['selectedClass']) && !empty($booking_data['program']['selectedClass'])) {
            $selected_class = esc_html($booking_data['program']['selectedClass']);
        }
        $selected_class = $selected_class ?: 'N/A';
        
        // Get preferences
        $experience = isset($booking_data['experience']) ? esc_html($booking_data['experience']) : 
                     (isset($booking_data['preferences']['experience']) ? esc_html($booking_data['preferences']['experience']) : 'N/A');
        $goals = isset($booking_data['goals']) ? esc_html($booking_data['goals']) : 
                (isset($booking_data['preferences']['goals']) ? esc_html($booking_data['preferences']['goals']) : 'N/A');
        $contact_preference = isset($booking_data['contactPreference']) ? esc_html($booking_data['contactPreference']) : 
                             (isset($booking_data['programInfo']['contactPreference']) ? esc_html($booking_data['programInfo']['contactPreference']) : 'N/A');
        
        // Get metadata
        $booking_id = isset($booking_data['bookingId']) ? esc_html($booking_data['bookingId']) : 
                     (isset($response_data['booking_id']) ? esc_html($response_data['booking_id']) : 'N/A');
        $status = isset($booking_data['status']) ? esc_html($booking_data['status']) : 'N/A';
        $submitted_at = isset($booking_data['submittedAt']) ? esc_html($booking_data['submittedAt']) : current_time('mysql');
        
        // Format the date
        $formatted_date = date('F j, Y \a\t g:i A', strtotime($submitted_at));
        
        $html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Trial Class Booking</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; background-color: #f5f5f5;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f5f5f5;">
        <tr>
            <td style="padding: 20px 0;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" style="margin: 0 auto; background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="padding: 30px 40px; background-color: #1a1a1a; border-radius: 8px 8px 0 0;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: 600;">New Trial Class Booking</h1>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td style="padding: 30px 40px;">
                            <p style="margin: 0 0 20px 0; color: #333333; font-size: 16px; line-height: 1.5;">A new trial class booking has been received.</p>
                            
                            <!-- Contact Information Section -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 20px 0;">
                                <tr>
                                    <td style="padding: 15px; background-color: #f8f9fa; border-left: 4px solid #1a1a1a;">
                                        <h2 style="margin: 0 0 15px 0; color: #1a1a1a; font-size: 18px; font-weight: 600;">Contact Information</h2>
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                            <tr>
                                                <td style="padding: 5px 0; color: #666666; font-size: 14px; width: 140px;"><strong>Name:</strong></td>
                                                <td style="padding: 5px 0; color: #333333; font-size: 14px;">' . $first_name . ' ' . $last_name . '</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 5px 0; color: #666666; font-size: 14px;"><strong>Email:</strong></td>
                                                <td style="padding: 5px 0; color: #333333; font-size: 14px;"><a href="mailto:' . esc_attr($email) . '" style="color: #1a1a1a; text-decoration: none;">' . $email . '</a></td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 5px 0; color: #666666; font-size: 14px;"><strong>Phone:</strong></td>
                                                <td style="padding: 5px 0; color: #333333; font-size: 14px;"><a href="tel:' . esc_attr($phone) . '" style="color: #1a1a1a; text-decoration: none;">' . $phone . '</a></td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Program Details Section -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 20px 0;">
                                <tr>
                                    <td style="padding: 15px; background-color: #f8f9fa; border-left: 4px solid #1a1a1a;">
                                        <h2 style="margin: 0 0 15px 0; color: #1a1a1a; font-size: 18px; font-weight: 600;">Program Details</h2>
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                            <tr>
                                                <td style="padding: 5px 0; color: #666666; font-size: 14px; width: 140px;"><strong>Program:</strong></td>
                                                <td style="padding: 5px 0; color: #333333; font-size: 14px;">' . ucfirst($group) . '</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 5px 0; color: #666666; font-size: 14px;"><strong>Age Group:</strong></td>
                                                <td style="padding: 5px 0; color: #333333; font-size: 14px;">' . ($age_group !== 'N/A' ? ucfirst(str_replace('_', ' ', $age_group)) : 'N/A') . '</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 5px 0; color: #666666; font-size: 14px;"><strong>Day:</strong></td>
                                                <td style="padding: 5px 0; color: #333333; font-size: 14px;">' . ucfirst($day) . '</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 5px 0; color: #666666; font-size: 14px;"><strong>Class:</strong></td>
                                                <td style="padding: 5px 0; color: #333333; font-size: 14px;">' . $selected_class . '</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Preferences Section -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 20px 0;">
                                <tr>
                                    <td style="padding: 15px; background-color: #f8f9fa; border-left: 4px solid #1a1a1a;">
                                        <h2 style="margin: 0 0 15px 0; color: #1a1a1a; font-size: 18px; font-weight: 600;">Preferences</h2>
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                            <tr>
                                                <td style="padding: 5px 0; color: #666666; font-size: 14px; width: 140px;"><strong>Experience:</strong></td>
                                                <td style="padding: 5px 0; color: #333333; font-size: 14px;">' . ucfirst($experience) . '</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 5px 0; color: #666666; font-size: 14px;"><strong>Goals:</strong></td>
                                                <td style="padding: 5px 0; color: #333333; font-size: 14px;">' . ($goals !== 'N/A' ? $goals : 'Not specified') . '</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 5px 0; color: #666666; font-size: 14px;"><strong>Contact Preference:</strong></td>
                                                <td style="padding: 5px 0; color: #333333; font-size: 14px;">' . ucfirst($contact_preference) . '</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Booking Metadata Section -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 20px 0;">
                                <tr>
                                    <td style="padding: 15px; background-color: #f8f9fa; border-left: 4px solid #1a1a1a;">
                                        <h2 style="margin: 0 0 15px 0; color: #1a1a1a; font-size: 18px; font-weight: 600;">Booking Information</h2>
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                            <tr>
                                                <td style="padding: 5px 0; color: #666666; font-size: 14px; width: 140px;"><strong>Booking ID:</strong></td>
                                                <td style="padding: 5px 0; color: #333333; font-size: 14px;">' . $booking_id . '</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 5px 0; color: #666666; font-size: 14px;"><strong>Contact Key:</strong></td>
                                                <td style="padding: 5px 0; color: #333333; font-size: 14px; font-family: monospace; font-size: 12px;">' . $contact_key . '</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 5px 0; color: #666666; font-size: 14px;"><strong>Event ID:</strong></td>
                                                <td style="padding: 5px 0; color: #333333; font-size: 14px;">' . $event_id . '</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 5px 0; color: #666666; font-size: 14px;"><strong>Status:</strong></td>
                                                <td style="padding: 5px 0; color: #333333; font-size: 14px;">' . ucfirst(str_replace('_', ' ', $status)) . '</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 5px 0; color: #666666; font-size: 14px;"><strong>Submitted:</strong></td>
                                                <td style="padding: 5px 0; color: #333333; font-size: 14px;">' . $formatted_date . '</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="padding: 20px 40px; background-color: #f8f9fa; border-radius: 0 0 8px 8px; border-top: 1px solid #e0e0e0;">
                            <p style="margin: 0; color: #999999; font-size: 12px; text-align: center;">This is an automated notification from the Clubworx System.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Format prospect notification email as HTML
     */
    private function format_prospect_email_html($prospect_data, $response_data) {
        // Extract data with fallbacks
        $first_name = isset($prospect_data['first_name']) ? esc_html($prospect_data['first_name']) : 
                     (isset($prospect_data['personal']['firstName']) ? esc_html($prospect_data['personal']['firstName']) : 'N/A');
        $last_name = isset($prospect_data['last_name']) ? esc_html($prospect_data['last_name']) : 
                    (isset($prospect_data['personal']['lastName']) ? esc_html($prospect_data['personal']['lastName']) : 'N/A');
        $email = isset($prospect_data['email']) ? esc_html($prospect_data['email']) : 
                (isset($prospect_data['personal']['email']) ? esc_html($prospect_data['personal']['email']) : 'N/A');
        $phone = isset($prospect_data['phone']) ? esc_html($prospect_data['phone']) : 
                (isset($prospect_data['personal']['phone']) ? esc_html($prospect_data['personal']['phone']) : 'N/A');
        
        // Get status and other info
        $status = isset($prospect_data['status']) ? esc_html($prospect_data['status']) : 'Initial Contact';
        $contact_preference = isset($prospect_data['contactPreference']) ? esc_html($prospect_data['contactPreference']) : 
                            (isset($prospect_data['programInfo']['contactPreference']) ? esc_html($prospect_data['programInfo']['contactPreference']) : 'N/A');
        $program_interest = isset($prospect_data['program_interest']) ? esc_html($prospect_data['program_interest']) : 
                          (isset($prospect_data['programInfo']['interestedIn']) ? esc_html($prospect_data['programInfo']['interestedIn']) : 'N/A');
        
        // Get contact key from response if available
        $contact_key = isset($response_data['contact_key']) ? esc_html($response_data['contact_key']) : 
                      (isset($prospect_data['contact_key']) ? esc_html($prospect_data['contact_key']) : 'N/A');
        
        // Get submission time
        $submitted_at = isset($prospect_data['submittedAt']) ? esc_html($prospect_data['submittedAt']) : current_time('mysql');
        $formatted_date = date('F j, Y \a\t g:i A', strtotime($submitted_at));
        
        $html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Contact Submission</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; background-color: #f5f5f5;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f5f5f5;">
        <tr>
            <td style="padding: 20px 0;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" style="margin: 0 auto; background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="padding: 30px 40px; background-color: #1a1a1a; border-radius: 8px 8px 0 0;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: 600;">New Contact Submission</h1>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td style="padding: 30px 40px;">
                            <p style="margin: 0 0 20px 0; color: #333333; font-size: 16px; line-height: 1.5;">A new contact/prospect submission has been received.</p>
                            
                            <!-- Contact Information Section -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 20px 0;">
                                <tr>
                                    <td style="padding: 15px; background-color: #f8f9fa; border-left: 4px solid #1a1a1a;">
                                        <h2 style="margin: 0 0 15px 0; color: #1a1a1a; font-size: 18px; font-weight: 600;">Contact Information</h2>
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                            <tr>
                                                <td style="padding: 5px 0; color: #666666; font-size: 14px; width: 140px;"><strong>Name:</strong></td>
                                                <td style="padding: 5px 0; color: #333333; font-size: 14px;">' . $first_name . ' ' . $last_name . '</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 5px 0; color: #666666; font-size: 14px;"><strong>Email:</strong></td>
                                                <td style="padding: 5px 0; color: #333333; font-size: 14px;"><a href="mailto:' . esc_attr($email) . '" style="color: #1a1a1a; text-decoration: none;">' . $email . '</a></td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 5px 0; color: #666666; font-size: 14px;"><strong>Phone:</strong></td>
                                                <td style="padding: 5px 0; color: #333333; font-size: 14px;"><a href="tel:' . esc_attr($phone) . '" style="color: #1a1a1a; text-decoration: none;">' . $phone . '</a></td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Submission Details Section -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 20px 0;">
                                <tr>
                                    <td style="padding: 15px; background-color: #f8f9fa; border-left: 4px solid #1a1a1a;">
                                        <h2 style="margin: 0 0 15px 0; color: #1a1a1a; font-size: 18px; font-weight: 600;">Submission Details</h2>
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                            <tr>
                                                <td style="padding: 5px 0; color: #666666; font-size: 14px; width: 140px;"><strong>Status:</strong></td>
                                                <td style="padding: 5px 0; color: #333333; font-size: 14px;">' . esc_html($status) . '</td>
                                            </tr>';
        
        if ($program_interest !== 'N/A') {
            $html .= '
                                            <tr>
                                                <td style="padding: 5px 0; color: #666666; font-size: 14px;"><strong>Program Interest:</strong></td>
                                                <td style="padding: 5px 0; color: #333333; font-size: 14px;">' . ucfirst($program_interest) . '</td>
                                            </tr>';
        }
        
        if ($contact_preference !== 'N/A') {
            $html .= '
                                            <tr>
                                                <td style="padding: 5px 0; color: #666666; font-size: 14px;"><strong>Contact Preference:</strong></td>
                                                <td style="padding: 5px 0; color: #333333; font-size: 14px;">' . ucfirst($contact_preference) . '</td>
                                            </tr>';
        }
        
        $html .= '
                                            <tr>
                                                <td style="padding: 5px 0; color: #666666; font-size: 14px;"><strong>Contact Key:</strong></td>
                                                <td style="padding: 5px 0; color: #333333; font-size: 14px; font-family: monospace; font-size: 12px;">' . $contact_key . '</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 5px 0; color: #666666; font-size: 14px;"><strong>Submitted:</strong></td>
                                                <td style="padding: 5px 0; color: #333333; font-size: 14px;">' . $formatted_date . '</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="padding: 20px 40px; background-color: #f8f9fa; border-radius: 0 0 8px 8px; border-top: 1px solid #e0e0e0;">
                            <p style="margin: 0; color: #999999; font-size: 12px; text-align: center;">This is an automated notification from the Clubworx System.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Get enhanced booking data by looking up prospect information
     */
    private function get_enhanced_booking_data($booking_data) {
        global $wpdb;
        
        // If we already have full data including program details, return as is
        if (isset($booking_data['personal']) || isset($booking_data['first_name'])) {
            // If booking data already has program details, use it directly
            if (isset($booking_data['program']) || isset($booking_data['programInfo'])) {
                return $booking_data;
            }
        }
        
        // Try to get prospect data from database if we have a contact_key
        if (isset($booking_data['contact_key'])) {
            $table_name = $wpdb->prefix . 'clubworx_bookings';
            $prospect_record = $wpdb->get_row($wpdb->prepare(
                "SELECT request_data FROM $table_name WHERE type = 'prospect' AND JSON_EXTRACT(response_data, '$.contact_key') = %s ORDER BY created_at DESC LIMIT 1",
                $booking_data['contact_key']
            ));
            
            if ($prospect_record) {
                $prospect_data = json_decode($prospect_record->request_data, true);
                if (is_array($prospect_data)) {
                    // Merge prospect data with booking data
                    // Booking data takes precedence to preserve program details
                    $merged = array_merge($prospect_data, $booking_data);
                    
                    // Preserve nested arrays from booking_data (program, preferences, etc.)
                    if (isset($booking_data['program'])) {
                        $merged['program'] = $booking_data['program'];
                    }
                    if (isset($booking_data['programInfo'])) {
                        $merged['programInfo'] = $booking_data['programInfo'];
                    }
                    if (isset($booking_data['preferences'])) {
                        $merged['preferences'] = $booking_data['preferences'];
                    }
                    if (isset($booking_data['personal'])) {
                        $merged['personal'] = $booking_data['personal'];
                    }
                    
                    return $merged;
                }
            }
        }
        
        return $booking_data;
    }
    
    /**
     * Send booking notification email
     */
    private function send_booking_notification($booking_data, $response_data, $location = null) {
        if (!is_array($location)) {
            $location = Clubworx_Locations::get(Clubworx_Locations::get_default_slug());
        }
        $admin_email = ($location && !empty($location['email']['admin_email']))
            ? $location['email']['admin_email']
            : get_option('admin_email');
        
        // Enhance booking data with prospect information if available
        $enhanced_data = $this->get_enhanced_booking_data($booking_data);
        
        $contact_key = isset($enhanced_data['contact_key']) ? $enhanced_data['contact_key'] : 'Unknown';
        $subject = 'New Trial Class Booking - ' . $contact_key;
        
        // Generate HTML email
        $html_message = $this->format_booking_email_html($enhanced_data, $response_data);
        
        // Generate plain text fallback
        $plain_text = "New trial class booking received:\n\n";
        $plain_text .= "Contact Key: " . $contact_key . "\n";
        $plain_text .= "Event ID: " . (isset($enhanced_data['event_id']) ? $enhanced_data['event_id'] : 'N/A') . "\n";
        $plain_text .= "Name: " . (isset($enhanced_data['first_name']) ? $enhanced_data['first_name'] : (isset($enhanced_data['personal']['firstName']) ? $enhanced_data['personal']['firstName'] : 'N/A')) . " ";
        $plain_text .= (isset($enhanced_data['last_name']) ? $enhanced_data['last_name'] : (isset($enhanced_data['personal']['lastName']) ? $enhanced_data['personal']['lastName'] : 'N/A')) . "\n";
        $plain_text .= "Email: " . (isset($enhanced_data['email']) ? $enhanced_data['email'] : (isset($enhanced_data['personal']['email']) ? $enhanced_data['personal']['email'] : 'N/A')) . "\n";
        $plain_text .= "Phone: " . (isset($enhanced_data['phone']) ? $enhanced_data['phone'] : (isset($enhanced_data['personal']['phone']) ? $enhanced_data['personal']['phone'] : 'N/A')) . "\n";
        
        // Set email headers for HTML
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
        );
        
        // Send email with HTML content
        $result = wp_mail($admin_email, $subject, $html_message, $headers);
        
        // Log email attempt
        $this->log_email($admin_email, $subject, $result, 'booking_notification');
        
        return $result;
    }
    
    /**
     * Send prospect notification email
     */
    private function send_prospect_notification($prospect_data, $response_data, $location = null) {
        if (!is_array($location)) {
            $location = Clubworx_Locations::get(Clubworx_Locations::get_default_slug());
        }
        $admin_email = ($location && !empty($location['email']['admin_email']))
            ? $location['email']['admin_email']
            : get_option('admin_email');
        
        $first_name = isset($prospect_data['first_name']) ? $prospect_data['first_name'] : 
                     (isset($prospect_data['personal']['firstName']) ? $prospect_data['personal']['firstName'] : 'Unknown');
        $last_name = isset($prospect_data['last_name']) ? $prospect_data['last_name'] : 
                    (isset($prospect_data['personal']['lastName']) ? $prospect_data['personal']['lastName'] : '');
        
        $subject = 'New Contact Submission - ' . $first_name . ' ' . $last_name;
        
        // Generate HTML email
        $html_message = $this->format_prospect_email_html($prospect_data, $response_data);
        
        // Generate plain text fallback
        $plain_text = "New contact/prospect submission received:\n\n";
        $plain_text .= "Name: " . $first_name . " " . $last_name . "\n";
        $plain_text .= "Email: " . (isset($prospect_data['email']) ? $prospect_data['email'] : (isset($prospect_data['personal']['email']) ? $prospect_data['personal']['email'] : 'N/A')) . "\n";
        $plain_text .= "Phone: " . (isset($prospect_data['phone']) ? $prospect_data['phone'] : (isset($prospect_data['personal']['phone']) ? $prospect_data['personal']['phone'] : 'N/A')) . "\n";
        $plain_text .= "Status: " . (isset($prospect_data['status']) ? $prospect_data['status'] : 'Initial Contact') . "\n";
        
        // Set email headers for HTML
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
        );
        
        // Send email with HTML content
        $result = wp_mail($admin_email, $subject, $html_message, $headers);
        
        // Log email attempt
        $this->log_email($admin_email, $subject, $result, 'prospect_notification');
        
        return $result;
    }
    
    /**
     * Log email send attempt
     */
    private function log_email($to, $subject, $success, $type = 'general', $error_message = '') {
        $log = get_option('clubworx_email_log', array());
        
        // Keep only last 50 entries
        if (count($log) >= 50) {
            $log = array_slice($log, -49);
        }
        
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'to' => $to,
            'subject' => $subject,
            'success' => $success,
            'type' => $type,
        );
        
        // If failed, try to get error details
        if (!$success) {
            if (!empty($error_message)) {
                $log_entry['error'] = $error_message;
            } else {
                global $phpmailer;
                if (isset($phpmailer) && isset($phpmailer->ErrorInfo) && !empty($phpmailer->ErrorInfo)) {
                    $log_entry['error'] = $phpmailer->ErrorInfo;
                } else {
                    $log_entry['error'] = 'Unknown error - check WordPress mail configuration. Ensure your hosting provider allows PHP mail() or configure SMTP.';
                }
            }
        }
        
        $log[] = $log_entry;
        update_option('clubworx_email_log', $log);
    }
    
    /**
     * Test email endpoint
     */
    public function test_email($request) {
        $data = $request->get_json_params();
        if (!is_array($data)) {
            $data = array();
        }

        $slug = isset($data['account']) ? sanitize_key($data['account']) : '';
        if ($slug === '') {
            $slug = Clubworx_Locations::get_default_slug();
        }
        $loc = Clubworx_Locations::get($slug);
        if ($loc === null) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Unknown Clubworx location',
            ), 400);
        }

        $test_email = isset($data['email']) ? sanitize_email($data['email']) : '';

        if (empty($test_email)) {
            $test_email = !empty($loc['email']['admin_email'])
                ? $loc['email']['admin_email']
                : get_option('admin_email');
        }
        
        // Validate email address
        if (!is_email($test_email)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Invalid email address: ' . $test_email,
                'email' => $test_email,
                'error_details' => 'The email address format is invalid.',
                'diagnostics' => $this->get_mail_diagnostics()
            ), 400);
        }
        
        $subject = __('Clubworx Integration — Test Email', 'clubworx-integration');
        $message = "This is a test email from the Clubworx plugin.\n\n";
        $message .= "If you receive this email, your WordPress email configuration is working correctly.\n\n";
        $message .= "Sent at: " . current_time('mysql') . "\n";
        $message .= "Plugin Version: " . CLUBWORX_INTEGRATION_VERSION;
        
        // Capture PHPMailer errors
        $mail_error = '';
        $phpmailer_error = '';
        
        // Hook into wp_mail_failed to capture errors
        add_action('wp_mail_failed', function($wp_error) use (&$mail_error) {
            $mail_error = $wp_error->get_error_message();
        }, 10, 1);
        
        // Attempt to send email (per-location SMTP via Clubworx_SMTP_Context)
        $result = false;
        Clubworx_SMTP_Context::with_location($loc, function () use (&$result, $test_email, $subject, $message) {
            $result = wp_mail($test_email, $subject, $message);
        });
        
        // Get PHPMailer error if available
        global $phpmailer;
        if (isset($phpmailer) && is_object($phpmailer)) {
            if (isset($phpmailer->ErrorInfo) && !empty($phpmailer->ErrorInfo)) {
                $phpmailer_error = $phpmailer->ErrorInfo;
            }
        }
        
        // Determine error message
        $error_message = '';
        $error_details = array();
        
        if (!$result) {
            if (!empty($mail_error)) {
                $error_message = $mail_error;
                $error_details[] = 'wp_mail_failed hook: ' . $mail_error;
            } elseif (!empty($phpmailer_error)) {
                $error_message = $phpmailer_error;
                $error_details[] = 'PHPMailer error: ' . $phpmailer_error;
            } else {
                $error_message = 'wp_mail() returned false, but no error details were captured.';
                $error_details[] = 'This usually means:';
                $error_details[] = '1. PHP mail() function is disabled on your server';
                $error_details[] = '2. SMTP is not configured and PHP mail() is not working';
                $error_details[] = '3. Hosting provider blocks outgoing emails';
                $error_details[] = '4. Email address is invalid or rejected by server';
            }
        }
        
        // Get diagnostics
        $diagnostics = $this->get_mail_diagnostics();
        
        // Log the test email
        $log_error = $error_message ?: ($result ? '' : 'Unknown error - wp_mail() returned false');
        $this->log_email($test_email, $subject, $result, 'test_email', $log_error);
        
        if ($result) {
            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'Test email sent successfully to ' . $test_email,
                'email' => $test_email,
                'diagnostics' => $diagnostics
            ), 200);
        } else {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Failed to send test email to ' . $test_email,
                'email' => $test_email,
                'error' => $error_message,
                'error_details' => $error_details,
                'diagnostics' => $diagnostics,
                'suggestions' => $this->get_mail_troubleshooting_suggestions()
            ), 500);
        }
    }
    
    /**
     * Get mail diagnostics
     */
    private function get_mail_diagnostics() {
        global $phpmailer;
        
        $diagnostics = array(
            'mail_function_exists' => function_exists('mail'),
            'wp_mail_function_exists' => function_exists('wp_mail'),
            'php_mail_function_enabled' => ini_get('sendmail_path') !== '' || function_exists('mail'),
        );
        
        // Check for SMTP plugins
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $smtp_plugins = array(
            'wp-mail-smtp/wp_mail_smtp.php' => 'WP Mail SMTP',
            'easy-wp-smtp/easy-wp-smtp.php' => 'Easy WP SMTP',
            'postman-smtp/postman-smtp.php' => 'Postman SMTP',
            'wp-smtp/wp-smtp.php' => 'WP SMTP',
        );
        
        $active_smtp_plugins = array();
        foreach ($smtp_plugins as $plugin => $name) {
            if (function_exists('is_plugin_active') && is_plugin_active($plugin)) {
                $active_smtp_plugins[] = $name . ' (' . $plugin . ')';
            }
        }
        
        $diagnostics['smtp_plugins_active'] = $active_smtp_plugins;

        $def_loc = Clubworx_Locations::get(Clubworx_Locations::get_default_slug());
        $smtp = ($def_loc && !empty($def_loc['smtp']) && is_array($def_loc['smtp'])) ? $def_loc['smtp'] : array();
        $plugin_smtp_enabled = !empty($smtp['enabled']);
        $plugin_smtp_configured = $plugin_smtp_enabled && !empty($smtp['host']) && !empty($smtp['port']);

        $diagnostics['plugin_smtp_enabled'] = $plugin_smtp_enabled;
        $diagnostics['plugin_smtp_configured'] = $plugin_smtp_configured;
        $diagnostics['smtp_configured'] = !empty($active_smtp_plugins) || $plugin_smtp_configured;

        if ($plugin_smtp_configured) {
            $diagnostics['plugin_smtp_host'] = isset($smtp['host']) ? $smtp['host'] : '';
            $diagnostics['plugin_smtp_port'] = isset($smtp['port']) ? $smtp['port'] : '';
            $diagnostics['plugin_smtp_encryption'] = isset($smtp['encryption']) ? $smtp['encryption'] : '';
        }
        
        // Check PHPMailer
        if (isset($phpmailer) && is_object($phpmailer)) {
            $diagnostics['phpmailer_available'] = true;
            $diagnostics['phpmailer_mailer'] = isset($phpmailer->Mailer) ? $phpmailer->Mailer : 'unknown';
            $diagnostics['phpmailer_host'] = isset($phpmailer->Host) ? $phpmailer->Host : 'not set';
        } else {
            $diagnostics['phpmailer_available'] = false;
        }
        
        // Check server configuration
        $diagnostics['server_name'] = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'unknown';
        $diagnostics['php_version'] = phpversion();
        $diagnostics['wordpress_version'] = get_bloginfo('version');
        
        return $diagnostics;
    }
    
    /**
     * Get mail troubleshooting suggestions
     */
    private function get_mail_troubleshooting_suggestions() {
        $def_loc = Clubworx_Locations::get(Clubworx_Locations::get_default_slug());
        $smtp = ($def_loc && !empty($def_loc['smtp']) && is_array($def_loc['smtp'])) ? $def_loc['smtp'] : array();
        $plugin_smtp_enabled = !empty($smtp['enabled']);
        
        $suggestions = array();
        
        if (!$plugin_smtp_enabled) {
            $suggestions[] = '1. Enable SMTP in Plugin Settings (recommended):';
            $suggestions[] = '   - Go to Settings → Clubworx → Settings tab';
            $suggestions[] = '   - Enable SMTP and configure your SMTP server settings';
            $suggestions[] = '   - This plugin has built-in SMTP support - no additional plugins needed!';
            $suggestions[] = '';
            $suggestions[] = '2. Or install an SMTP plugin:';
            $suggestions[] = '   - WP Mail SMTP (https://wordpress.org/plugins/wp-mail-smtp/)';
            $suggestions[] = '   - Easy WP SMTP (https://wordpress.org/plugins/easy-wp-smtp/)';
            $suggestions[] = '   These plugins allow you to use Gmail, Outlook, or your hosting provider\'s SMTP server.';
        } else {
            $suggestions[] = '1. Check SMTP Configuration:';
            $suggestions[] = '   - Verify SMTP host, port, and encryption settings are correct';
            $suggestions[] = '   - Ensure SMTP username and password are correct';
            $suggestions[] = '   - For Gmail, you may need to use an App Password instead of your regular password';
            $suggestions[] = '   - Check that your SMTP server allows connections from your server';
            $suggestions[] = '';
            $suggestions[] = '2. Common SMTP Issues:';
            $suggestions[] = '   - Gmail: Use port 587 with TLS, and create an App Password';
            $suggestions[] = '   - Outlook: Use port 587 with TLS';
            $suggestions[] = '   - Some servers block port 25 - use 587 or 465 instead';
        }
        
        $suggestions[] = '';
        $suggestions[] = '3. Check with your hosting provider:';
        $suggestions[] = '   - Some hosting providers disable PHP mail() function';
        $suggestions[] = '   - Ask if they allow PHP mail() or if you need to use SMTP';
        $suggestions[] = '   - Some providers require emails to be sent from your domain only';
        $suggestions[] = '';
        $suggestions[] = '4. Check server logs:';
        $suggestions[] = '   - Check WordPress debug log (if WP_DEBUG is enabled)';
        $suggestions[] = '   - Check server error logs for mail-related errors';
        $suggestions[] = '';
        $suggestions[] = '5. Verify email address:';
        $suggestions[] = '   - Ensure the email address is valid and accessible';
        $suggestions[] = '   - Check spam/junk folder';
        $suggestions[] = '   - Try a different email address';
        
        return $suggestions;
    }
    
    /**
     * Check for plugin updates from GitHub
     */
    public function check_for_updates($request) {
        if (!class_exists('Clubworx_GitHub_Updater')) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'GitHub updater class not found'
            ), 500);
        }
        
        $updater = Clubworx_GitHub_Updater::get_instance();
        
        // Force check and inject update into WordPress update system
        $latest_release = $updater->force_check_updates();
        
        $current_version = defined('CLUBWORX_INTEGRATION_VERSION') ? CLUBWORX_INTEGRATION_VERSION : '1.0.0';
        
        if (!$latest_release) {
            // Check for draft releases
            $all_releases = $updater->get_all_releases();
            $draft_releases = array();
            $published_releases = array();
            
            if (is_array($all_releases)) {
                foreach ($all_releases as $rel) {
                    if (isset($rel['draft']) && $rel['draft'] === true) {
                        $draft_releases[] = array(
                            'tag' => isset($rel['tag_name']) ? $rel['tag_name'] : 'unknown',
                            'name' => isset($rel['name']) ? $rel['name'] : 'Untitled',
                        );
                    } else {
                        $published_releases[] = array(
                            'tag' => isset($rel['tag_name']) ? $rel['tag_name'] : 'unknown',
                            'name' => isset($rel['name']) ? $rel['name'] : 'Untitled',
                            'version' => isset($rel['tag_name']) ? ltrim($rel['tag_name'], 'v') : 'unknown',
                        );
                    }
                }
            }
            
            // Build error details
            $error_details = array();
            $error_details[] = 'The GitHub API /latest endpoint returned 404, which usually means:';
            $error_details[] = '1. The release is still a DRAFT (drafts don\'t appear in /latest endpoint)';
            $error_details[] = '2. The repository is private (requires authentication)';
            $error_details[] = '3. No releases have been published yet';
            $error_details[] = '';
            
            if (!empty($draft_releases)) {
                $error_details[] = '⚠️ DRAFT RELEASES FOUND (these won\'t be detected):';
                foreach ($draft_releases as $draft) {
                    $error_details[] = '   - ' . $draft['tag'] . ' (' . $draft['name'] . ')';
                }
                $error_details[] = '';
                $error_details[] = '💡 SOLUTION: Publish the GitHub release draft for your configured repository (Settings → GitHub).';
            }
            
            if (!empty($published_releases)) {
                $error_details[] = '✅ Published releases found:';
                foreach (array_slice($published_releases, 0, 5) as $pub) {
                    $error_details[] = '   - ' . $pub['tag'] . ' (version ' . $pub['version'] . ')';
                }
                if (count($published_releases) > 5) {
                    $error_details[] = '   ... and ' . (count($published_releases) - 5) . ' more';
                }
            }
            
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Failed to fetch latest release from GitHub',
                'current_version' => $current_version,
                'github_configured' => true,
                'error_details' => $error_details,
                'draft_releases' => $draft_releases,
                'published_releases' => $published_releases,
                'github_url' => 'https://github.com/'
            ), 500);
        }
        
        $update_available = version_compare($current_version, $latest_release['version'], '<');
        
        // Log for debugging
        error_log(sprintf(
            'Clubworx Update Check: Current=%s, Latest=%s, Update Available=%s',
            $current_version,
            $latest_release['version'],
            $update_available ? 'YES' : 'NO'
        ));
        
        return new WP_REST_Response(array(
            'success' => true,
            'current_version' => $current_version,
            'latest_version' => $latest_release['version'],
            'update_available' => $update_available,
            'release_url' => $latest_release['url'],
            'release_notes' => $latest_release['release_notes'],
            'tag_name' => isset($latest_release['tag_name']) ? $latest_release['tag_name'] : '',
            'message' => $update_available 
                ? sprintf('Update available: %s → %s', $current_version, $latest_release['version'])
                : sprintf('Plugin is up to date (version %s)', $current_version),
            'debug_info' => array(
                'version_compare_result' => version_compare($current_version, $latest_release['version']),
                'transient_set' => true
            )
        ), 200);
    }
    
    /**
     * Timetable shortcode handler
     */
    public function timetable_shortcode($atts) {
        $atts = shortcode_atts(array(
            'title' => 'Class Schedule',
            'show_title' => 'true',
            'layout' => 'grid', // grid, list, compact, desktop
            'days' => 'all', // all, weekdays, weekend, or comma-separated list
            'categories' => 'all', // all, kids, adults, women, or comma-separated list
            'show_times' => 'true',
            'show_categories' => 'true',
            'max_days' => '7',
            'timezone' => '',
            'show_filters' => 'true',
            'show_now_banner' => 'true',
            'default_duration_minutes' => '',
            'account' => '',
        ), $atts);

        global $post;
        $post_id = is_a($post, 'WP_Post') ? (int) $post->ID : 0;
        $slugs = Clubworx_Locations::expand_account_slugs(isset($atts['account']) ? $atts['account'] : '', $post_id ? $post_id : null);

        $slug_schedules = array();
        foreach ($slugs as $slug) {
            $slug_schedules[$slug] = $this->get_clubworx_schedule($slug);
        }
        $schedule = $this->merge_schedule_trees($slug_schedules);

        if (!$schedule || empty($schedule)) {
            return '<div class="clubworx-timetable-error">Schedule not available at this time.</div>';
        }

        $loc_style = Clubworx_Locations::get($slugs[0]);
        $tz_default = function_exists('wp_timezone_string') ? wp_timezone_string() : 'UTC';
        $tt_defaults = Clubworx_Locations::default_location_data($tz_default);
        $tt = ($loc_style && isset($loc_style['timetable']) && is_array($loc_style['timetable']))
            ? $loc_style['timetable']
            : $tt_defaults['timetable'];

        $default_tz = isset($tt['timezone']) ? $tt['timezone'] : 'Australia/Sydney';
        $timezone = !empty($atts['timezone']) ? $atts['timezone'] : $default_tz;
        $timezone = $this->sanitize_timetable_timezone($timezone);

        $default_dur = isset($tt['default_duration_minutes']) ? absint($tt['default_duration_minutes']) : 60;
        if ($default_dur < 15 || $default_dur > 240) {
            $default_dur = 60;
        }
        $duration_minutes = $default_dur;
        if ($atts['default_duration_minutes'] !== '' && is_numeric($atts['default_duration_minutes'])) {
            $duration_minutes = max(15, min(240, intval($atts['default_duration_minutes'])));
        }

        $tt_colors = array(
            'primary' => isset($tt['primary_color']) ? $tt['primary_color'] : '#1914a6',
            'accent' => isset($tt['accent_color']) ? $tt['accent_color'] : '#ffbe00',
            'text' => isset($tt['text_color']) ? $tt['text_color'] : '#333333',
            'surface' => isset($tt['surface_color']) ? $tt['surface_color'] : '#ffffff',
            'title' => isset($tt['title_color']) ? $tt['title_color'] : '#2c3e50',
            'border' => isset($tt['border_color']) ? $tt['border_color'] : '#e1e8ed',
            'class_card_bg' => isset($tt['class_card_bg_color']) ? $tt['class_card_bg_color'] : '#f8f9fa',
            'class_card_text' => isset($tt['class_card_text_color']) ? $tt['class_card_text_color'] : '#34495e',
        );
        
        $show_filters = ($atts['show_filters'] === 'true' || $atts['show_filters'] === true || $atts['show_filters'] === '1');
        $show_now_banner = ($atts['show_now_banner'] === 'true' || $atts['show_now_banner'] === true || $atts['show_now_banner'] === '1');
        
        $plugin_dir = defined('CLUBWORX_INTEGRATION_PLUGIN_DIR') ? CLUBWORX_INTEGRATION_PLUGIN_DIR : dirname(dirname(__FILE__)) . '/';
        if (defined('CLUBWORX_INTEGRATION_PLUGIN_URL')) {
            $plugin_url = CLUBWORX_INTEGRATION_PLUGIN_URL;
        } else {
            $plugin_url = plugin_dir_url(dirname(dirname(__FILE__)) . '/clubworx-integration.php');
        }
        
        // Enqueue the timetable CSS with version number to prevent caching
        wp_enqueue_style('clubworx-timetable-style', plugin_dir_url(__FILE__) . '../assets/css/timetable.css', array(), filemtime(plugin_dir_path(__FILE__) . '../assets/css/timetable.css'));
        
        // Add inline styles to force light mode (highest priority)
        $inline_css = '
            .clubworx-timetable, .clubworx-timetable * {
                color-scheme: light !important;
            }
            .clubworx-timetable {
                background: var(--clubworx-tt-surface, #ffffff) !important;
                color: var(--clubworx-tt-text, #333333) !important;
            }
            .clubworx-timetable-content {
                background: var(--clubworx-tt-surface, #ffffff) !important;
            }
            .clubworx-timetable-day {
                background: var(--clubworx-tt-surface, #ffffff) !important;
                color: var(--clubworx-tt-text, #333333) !important;
                border-color: var(--clubworx-tt-border, #e1e8ed) !important;
            }
            .clubworx-timetable-day::before {
                content: none !important;
                display: none !important;
                background: none !important;
                height: 0 !important;
            }
            .clubworx-timetable-title, .clubworx-timetable-day-title {
                color: var(--clubworx-tt-title, #2c3e50) !important;
                border-color: var(--clubworx-tt-border, #e1e8ed) !important;
            }
            .clubworx-timetable-class {
                background: var(--clubworx-tt-class-card-bg, #f8f9fa) !important;
                color: var(--clubworx-tt-class-card-text, #34495e) !important;
            }
            .clubworx-timetable-class-name {
                color: var(--clubworx-tt-class-card-text, #34495e) !important;
            }
            .clubworx-timetable-time {
                background: rgba(255, 255, 255, 0.8) !important;
                color: #2c3e50 !important;
            }
            .clubworx-timetable-category {
                background: rgba(255, 255, 255, 0.9) !important;
                color: #7f8c8d !important;
            }
            .clubworx-timetable-now-banner {
                background: var(--clubworx-tt-primary, #1914a6) !important;
                color: #f8f9fa !important;
            }
            .clubworx-timetable-now-detail {
                color: #ffffff !important;
            }
            .clubworx-timetable-now-label {
                color: var(--clubworx-tt-accent, #ffbe00) !important;
            }
            .clubworx-timetable-filter {
                background: #ffffff !important;
                color: #34495e !important;
                border-color: #dce4ec !important;
            }
            .clubworx-timetable-filter.is-active {
                background: var(--clubworx-tt-primary, #1914a6) !important;
                color: #f8f9fa !important;
                border-color: var(--clubworx-tt-primary, #1914a6) !important;
            }
            .clubworx-timetable-class--highlight {
                box-shadow: 0 0 0 2px var(--clubworx-tt-accent, #ffbe00) !important;
            }
        ';
        wp_add_inline_style('clubworx-timetable-style', $inline_css);
        
        // Enqueue a script handle for inline script
        wp_enqueue_script('clubworx-timetable-light-mode', '', array(), '1.0.0', true);
        
        // Add JavaScript to force light mode after page load
        $inline_js = "
        (function() {
            function forceTimetableLightMode() {
                const timetables = document.querySelectorAll('.clubworx-timetable');
                timetables.forEach(function(timetable) {
                    // Remove any dark mode classes
                    timetable.classList.remove('dark-mode', 'dark', 'theme-dark');
                    timetable.removeAttribute('data-theme');
                    
                    // Force inline styles
                    timetable.style.setProperty('background', '#ffffff', 'important');
                    timetable.style.setProperty('color', '#333', 'important');
                    timetable.style.setProperty('color-scheme', 'light', 'important');
                    
                    // Force styles on all child elements
                    const allElements = timetable.querySelectorAll('*');
                    allElements.forEach(function(el) {
                        el.style.setProperty('color-scheme', 'light', 'important');
                    });
                    
                    // Specific elements
                    const content = timetable.querySelector('.clubworx-timetable-content');
                    if (content) {
                        content.style.setProperty('background', '#ffffff', 'important');
                    }
                    
                    const days = timetable.querySelectorAll('.clubworx-timetable-day');
                    days.forEach(function(day) {
                        day.style.setProperty('background', 'var(--clubworx-tt-surface, #ffffff)', 'important');
                        day.style.setProperty('color', 'var(--clubworx-tt-text, #333333)', 'important');
                        day.style.setProperty('border-color', 'var(--clubworx-tt-border, #e1e8ed)', 'important');
                    });
                    
                    const classes = timetable.querySelectorAll('.clubworx-timetable-class');
                    classes.forEach(function(cls) {
                        cls.style.setProperty('background', 'var(--clubworx-tt-class-card-bg, #f8f9fa)', 'important');
                        cls.style.setProperty('color', 'var(--clubworx-tt-class-card-text, #34495e)', 'important');
                    });
                    
                    const titles = timetable.querySelectorAll('.clubworx-timetable-title, .clubworx-timetable-day-title');
                    titles.forEach(function(title) {
                        title.style.setProperty('color', 'var(--clubworx-tt-title, #2c3e50)', 'important');
                        title.style.setProperty('border-color', 'var(--clubworx-tt-border, #e1e8ed)', 'important');
                    });
                    
                    const times = timetable.querySelectorAll('.clubworx-timetable-time');
                    times.forEach(function(time) {
                        time.style.setProperty('background', 'rgba(255, 255, 255, 0.8)', 'important');
                        time.style.setProperty('color', '#2c3e50', 'important');
                    });
                    
                    const names = timetable.querySelectorAll('.clubworx-timetable-class-name');
                    names.forEach(function(name) {
                        name.style.setProperty('color', '#34495e', 'important');
                    });
                    
                    const categories = timetable.querySelectorAll('.clubworx-timetable-category');
                    categories.forEach(function(cat) {
                        cat.style.setProperty('background', 'rgba(255, 255, 255, 0.9)', 'important');
                        cat.style.setProperty('color', '#7f8c8d', 'important');
                    });
                });
            }
            
            // Run immediately
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', forceTimetableLightMode);
            } else {
                forceTimetableLightMode();
            }
            
            // Also run after a short delay to catch dynamically loaded content
            setTimeout(forceTimetableLightMode, 100);
            setTimeout(forceTimetableLightMode, 500);
            
            // Watch for new timetable elements added dynamically
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1) {
                            if (node.classList && node.classList.contains('clubworx-timetable')) {
                                forceTimetableLightMode();
                            } else if (node.querySelector && node.querySelector('.clubworx-timetable')) {
                                forceTimetableLightMode();
                            }
                        }
                    });
                });
            });
            
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        })();
        ";
        wp_add_inline_script('clubworx-timetable-light-mode', $inline_js);
        
        $timetable_js_path = $plugin_dir . 'assets/js/timetable.js';
        if (file_exists($timetable_js_path)) {
            wp_enqueue_script(
                'clubworx-timetable',
                $plugin_url . 'assets/js/timetable.js',
                array(),
                filemtime($timetable_js_path),
                true
            );
        }
        
        // Start output buffering
        ob_start();
        
        $instance_id = function_exists('wp_unique_id') ? wp_unique_id('clubworx-timetable-') : uniqid('clubworx-timetable-', false);
        
        $this->generate_timetable_html(
            $schedule,
            $atts,
            array(
                'instance_id' => $instance_id,
                'timezone' => $timezone,
                'duration_minutes' => $duration_minutes,
                'show_filters' => $show_filters,
                'show_now_banner' => $show_now_banner,
                'tt_colors' => $tt_colors,
                'day_labels' => array(
                    'monday' => __('Monday', 'clubworx-integration'),
                    'tuesday' => __('Tuesday', 'clubworx-integration'),
                    'wednesday' => __('Wednesday', 'clubworx-integration'),
                    'thursday' => __('Thursday', 'clubworx-integration'),
                    'friday' => __('Friday', 'clubworx-integration'),
                    'saturday' => __('Saturday', 'clubworx-integration'),
                    'sunday' => __('Sunday', 'clubworx-integration'),
                ),
            )
        );
        
        return ob_get_clean();
    }
    
    /**
     * Validate IANA timezone string for timetable JS.
     *
     * @param string $timezone Raw timezone string.
     * @return string
     */
    private function sanitize_timetable_timezone($timezone) {
        $timezone = sanitize_text_field($timezone);
        if ($timezone === '') {
            return 'Australia/Sydney';
        }
        return in_array($timezone, timezone_identifiers_list(), true) ? $timezone : 'Australia/Sydney';
    }
    
    /**
     * Build weekly class map for timetable.js (all categories).
     *
     * @param array $schedule Schedule tree from ClubWorx or fallback.
     * @return array { classesByDay: array, weekOrder: array }
     */
    private function build_timetable_week_classes_json($schedule) {
        $week = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday');
        $atts_all = array(
            'categories' => 'all',
            'days' => 'all',
            'max_days' => '7',
            'show_times' => 'true',
            'show_categories' => 'true',
        );
        $classes_by_day = array();
        foreach ($week as $d) {
            $classes_by_day[$d] = array();
            foreach ($this->get_day_classes($schedule, $d, $atts_all) as $class) {
                $classes_by_day[$d][] = array(
                    'startMinutes' => (int) $class['sort_time'],
                    'name' => $class['name'],
                    'time' => $class['time'],
                    'categoryClass' => $class['category_class'],
                    'categoryLabel' => $class['category'],
                );
            }
        }
        return array(
            'classesByDay' => $classes_by_day,
            'weekOrder' => $week,
        );
    }
    
    /**
     * Generate timetable HTML
     *
     * @param array $schedule Schedule data.
     * @param array $atts Shortcode attributes.
     * @param array $opts instance_id, timezone, duration_minutes, show_filters, show_now_banner, day_labels.
     */
    private function generate_timetable_html($schedule, $atts, $opts) {
        $days = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday');
        $dayNames = isset($opts['day_labels']) && is_array($opts['day_labels']) ? $opts['day_labels'] : array(
            'monday' => 'Monday',
            'tuesday' => 'Tuesday',
            'wednesday' => 'Wednesday',
            'thursday' => 'Thursday',
            'friday' => 'Friday',
            'saturday' => 'Saturday',
            'sunday' => 'Sunday',
        );
        
        $category_filter_order = array(
            'kids-under6' => __('Kids (Under 6)', 'clubworx-integration'),
            'kids-over6' => __('Kids (6+)', 'clubworx-integration'),
            'adults-general' => __('Adults General', 'clubworx-integration'),
            'adults-foundations' => __('Adults Foundations', 'clubworx-integration'),
            'women' => __('Women', 'clubworx-integration'),
        );
        
        // Filter days if specified
        if ($atts['days'] !== 'all') {
            if ($atts['days'] === 'weekdays') {
                $days = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday');
            } elseif ($atts['days'] === 'weekend') {
                $days = array('saturday', 'sunday');
            } else {
                $requested_days = array_map('trim', explode(',', $atts['days']));
                $days = array_intersect($days, $requested_days);
            }
        }
        
        // Limit number of days
        if ($atts['max_days'] !== 'all' && is_numeric($atts['max_days'])) {
            $days = array_slice($days, 0, intval($atts['max_days']));
        }
        
        $seen_categories = array();
        foreach ($days as $day_key) {
            foreach ($this->get_day_classes($schedule, $day_key, $atts) as $class_row) {
                $slug = $class_row['category_class'];
                if (!isset($seen_categories[$slug])) {
                    $seen_categories[$slug] = $class_row['category'];
                }
            }
        }
        
        $filter_categories = array();
        foreach ($category_filter_order as $slug => $label_default) {
            if (isset($seen_categories[$slug])) {
                $filter_categories[] = array(
                    'slug' => $slug,
                    'label' => $seen_categories[$slug],
                );
            }
        }
        
        $week_json = $this->build_timetable_week_classes_json($schedule);
        $tc = isset($opts['tt_colors']) && is_array($opts['tt_colors']) ? $opts['tt_colors'] : array();
        $tt_primary = (!empty($tc['primary']) && preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $tc['primary'])) ? $tc['primary'] : '#1914a6';
        $tt_accent = (!empty($tc['accent']) && preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $tc['accent'])) ? $tc['accent'] : '#ffbe00';
        $tt_text = (!empty($tc['text']) && preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $tc['text'])) ? $tc['text'] : '#333333';
        $tt_surface = (!empty($tc['surface']) && preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $tc['surface'])) ? $tc['surface'] : '#ffffff';
        $tt_title = (!empty($tc['title']) && preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $tc['title'])) ? $tc['title'] : '#2c3e50';
        $tt_border = (!empty($tc['border']) && preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $tc['border'])) ? $tc['border'] : '#e1e8ed';
        $tt_class_card_bg = (!empty($tc['class_card_bg']) && preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $tc['class_card_bg'])) ? $tc['class_card_bg'] : '#f8f9fa';
        $tt_class_card_text = (!empty($tc['class_card_text']) && preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $tc['class_card_text'])) ? $tc['class_card_text'] : '#34495e';
        
        $client_config = array(
            'instanceId' => $opts['instance_id'],
            'timezone' => $opts['timezone'],
            'durationMinutes' => (int) $opts['duration_minutes'],
            'classesByDay' => $week_json['classesByDay'],
            'weekOrder' => $week_json['weekOrder'],
            'dayLabels' => $dayNames,
            'filterCategories' => $filter_categories,
            'showFilters' => !empty($opts['show_filters']),
            'showNowBanner' => !empty($opts['show_now_banner']),
        );
        
        ?>
        <div id="<?php echo esc_attr($opts['instance_id']); ?>" class="clubworx-timetable clubworx-timetable-<?php echo esc_attr($atts['layout']); ?>" style="--clubworx-tt-primary: <?php echo esc_attr($tt_primary); ?>; --clubworx-tt-accent: <?php echo esc_attr($tt_accent); ?>; --clubworx-tt-text: <?php echo esc_attr($tt_text); ?>; --clubworx-tt-surface: <?php echo esc_attr($tt_surface); ?>; --clubworx-tt-title: <?php echo esc_attr($tt_title); ?>; --clubworx-tt-border: <?php echo esc_attr($tt_border); ?>; --clubworx-tt-class-card-bg: <?php echo esc_attr($tt_class_card_bg); ?>; --clubworx-tt-class-card-text: <?php echo esc_attr($tt_class_card_text); ?>; background: var(--clubworx-tt-surface) !important; color: var(--clubworx-tt-text) !important; color-scheme: light !important;">
            <script type="application/json" class="clubworx-timetable-config"><?php echo wp_json_encode($client_config); ?></script>
            <div class="clubworx-timetable-header">
                <?php if ($atts['show_title'] === 'true' && !empty($atts['title'])): ?>
                    <h3 class="clubworx-timetable-title"><?php echo esc_html($atts['title']); ?></h3>
                <?php endif; ?>
                
                <?php if (!empty($opts['show_now_banner'])): ?>
                    <div class="clubworx-timetable-now-banner" aria-live="polite">
                        <div class="clubworx-timetable-now-banner-inner">
                            <span class="clubworx-timetable-now-label"></span>
                            <span class="clubworx-timetable-now-detail"></span>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($opts['show_filters']) && !empty($filter_categories)): ?>
                    <div class="clubworx-timetable-filters" role="toolbar" aria-label="<?php echo esc_attr(__('Filter by class type', 'clubworx-integration')); ?>">
                        <button type="button" class="clubworx-timetable-filter is-active" data-category="all" aria-pressed="true"><?php esc_html_e('All', 'clubworx-integration'); ?></button>
                        <?php foreach ($filter_categories as $fc): ?>
                            <button type="button" class="clubworx-timetable-filter" data-category="<?php echo esc_attr($fc['slug']); ?>" aria-pressed="false"><?php echo esc_html($fc['label']); ?></button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="clubworx-timetable-content">
                <?php foreach ($days as $day): ?>
                    <?php
                    $dayClasses = $this->get_day_classes($schedule, $day, $atts);
                    if (!empty($dayClasses)):
                    ?>
                        <div class="clubworx-timetable-day">
                            <h4 class="clubworx-timetable-day-title"><?php echo esc_html($dayNames[$day]); ?></h4>
                            <div class="clubworx-timetable-classes">
                                <?php foreach ($dayClasses as $class): ?>
                                    <div class="clubworx-timetable-class <?php echo esc_attr($class['category_class']); ?>"
                                        data-day="<?php echo esc_attr($day); ?>"
                                        data-category="<?php echo esc_attr($class['category_class']); ?>"
                                        data-start-minutes="<?php echo (int) $class['sort_time']; ?>">
                                        <?php if ($atts['show_times'] === 'true'): ?>
                                            <span class="clubworx-timetable-time"><?php echo esc_html($class['time']); ?></span>
                                        <?php endif; ?>
                                        <span class="clubworx-timetable-class-name"><?php echo esc_html($class['name']); ?></span>
                                        <?php if ($atts['show_categories'] === 'true' && !empty($class['category'])): ?>
                                            <span class="clubworx-timetable-category"><?php echo esc_html($class['category']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if (!empty($opts['show_filters']) && !empty($filter_categories)): ?>
                                <p class="clubworx-timetable-day-empty" hidden><?php esc_html_e('No classes match this filter.', 'clubworx-integration'); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Pricing shortcode handler
     */
    public function pricing_shortcode($atts) {
        $atts = shortcode_atts(array(
            'category' => 'all', // adults, kids, women, all
            'includes' => '', // comma-separated keywords to include
            'excludes' => '', // comma-separated keywords to exclude
            'layout' => 'cards', // cards, table
            'button_text' => 'Sign Up',
            'button_link' => '#booking-form',
            'highlight' => '', // comma-separated plan names to highlight
            'title' => 'Membership Plans',
            'show_title' => 'true',
            'account' => '',
        ), $atts);

        // Get membership plans
        $plans_response = $this->get_membership_plans_from_api(isset($atts['account']) ? $atts['account'] : '');
        $plans = isset($plans_response['plans']) ? $plans_response['plans'] : array();
        
        if (empty($plans)) {
            return '<div class="clubworx-pricing-error">Pricing information not available at this time.</div>';
        }
        
        // Filter plans based on attributes
        $filtered_plans = $this->filter_pricing_plans($plans, $atts);
        
        if (empty($filtered_plans)) {
            return '<div class="clubworx-pricing-error">No plans match the specified criteria.</div>';
        }
        
        // Enqueue pricing CSS
        wp_enqueue_style('clubworx-pricing-style', plugin_dir_url(__FILE__) . '../assets/css/pricing.css', array(), '1.0.0');
        
        // Start output buffering
        ob_start();
        
        // Generate pricing HTML
        $this->generate_pricing_html($filtered_plans, $atts);
        
        return ob_get_clean();
    }
    
    /**
     * Get membership plans from API
     */
    private function get_membership_plans_from_api($account = '') {
        $api_url = rest_url('clubworx/v1/membership-plans');
        if (is_string($account) && $account !== '') {
            $api_url = add_query_arg('account', $account, $api_url);
        }
        $response = wp_remote_get($api_url, array(
            'timeout' => 30,
        ));
        
        if (is_wp_error($response)) {
            error_log('Clubworx Integration: Failed to fetch membership plans: ' . $response->get_error_message());
            return array('plans' => array());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        return $data ? $data : array('plans' => array());
    }
    
    /**
     * Filter pricing plans based on shortcode attributes
     */
    private function filter_pricing_plans($plans, $atts) {
        $filtered = array();
        
        foreach ($plans as $plan) {
            // Only show 2025 plans (or 2026 if they exist)
            if (strpos($plan['name'], '2025') === false && strpos($plan['name'], '2026') === false) {
                continue;
            }
            
            // Category filter
            if ($atts['category'] !== 'all' && $plan['category'] !== $atts['category']) {
                continue;
            }
            
            // Includes filter
            if (!empty($atts['includes'])) {
                $includes = array_map('trim', explode(',', $atts['includes']));
                $name_lower = strtolower($plan['name']);
                $matches_include = false;
                foreach ($includes as $include) {
                    if (strpos($name_lower, strtolower($include)) !== false) {
                        $matches_include = true;
                        break;
                    }
                }
                if (!$matches_include) {
                    continue;
                }
            }
            
            // Excludes filter
            if (!empty($atts['excludes'])) {
                $excludes = array_map('trim', explode(',', $atts['excludes']));
                $name_lower = strtolower($plan['name']);
                $matches_exclude = false;
                foreach ($excludes as $exclude) {
                    if (strpos($name_lower, strtolower($exclude)) !== false) {
                        $matches_exclude = true;
                        break;
                    }
                }
                if ($matches_exclude) {
                    continue;
                }
            }
            
            $filtered[] = $plan;
        }
        
        return $filtered;
    }
    
    /**
     * Generate pricing HTML
     */
    private function generate_pricing_html($plans, $atts) {
        $highlight_plans = !empty($atts['highlight']) ? array_map('trim', explode(',', $atts['highlight'])) : array();
        
        // Group plans by category
        $grouped_plans = array();
        foreach ($plans as $plan) {
            $category = $plan['category'];
            if (!isset($grouped_plans[$category])) {
                $grouped_plans[$category] = array();
            }
            $grouped_plans[$category][] = $plan;
        }
        
        // Define category order and labels
        $category_order = array('adults', 'kids', 'family');
        $category_labels = array(
            'adults' => 'Adult Pricing',
            'kids' => 'Kids & Teens Pricing', 
            'family' => 'Family Pricing',
            'women' => 'Women\'s Pricing',
            'general' => 'General Pricing'
        );
        ?>
        <div class="clubworx-pricing clubworx-pricing-<?php echo esc_attr($atts['layout']); ?>">
            <?php if ($atts['show_title'] === 'true' && !empty($atts['title'])): ?>
                <h3 class="clubworx-pricing-title"><?php echo esc_html($atts['title']); ?></h3>
            <?php endif; ?>
            
            <?php foreach ($category_order as $category): ?>
                <?php if (isset($grouped_plans[$category]) && !empty($grouped_plans[$category])): ?>
                    <div class="clubworx-pricing-category">
                        <h4 class="clubworx-pricing-category-title"><?php echo esc_html($category_labels[$category]); ?></h4>
                        <div class="clubworx-pricing-cards">
                            <?php foreach ($grouped_plans[$category] as $plan): ?>
                                <?php 
                                // Auto-highlight UNLIMITED plans or manually highlighted plans
                                $is_highlighted = in_array($plan['name'], $highlight_plans) || 
                                                stripos($plan['name'], 'unlimited') !== false;
                                $card_class = 'clubworx-pricing-card';
                                if ($is_highlighted) {
                                    $card_class .= ' clubworx-pricing-card-highlighted';
                                }
                                ?>
                                <div class="<?php echo esc_attr($card_class); ?>">
                                    <?php if ($is_highlighted): ?>
                                        <div class="clubworx-pricing-badge">Popular</div>
                                    <?php endif; ?>
                                    
                                    <h4 class="clubworx-pricing-plan-name"><?php echo esc_html($plan['name']); ?></h4>
                                    
                                    <div class="clubworx-pricing-price">
                                        <span class="clubworx-pricing-currency"><?php echo esc_html($plan['currency']); ?></span>
                                        <span class="clubworx-pricing-amount"><?php echo number_format($plan['price'], 0); ?></span>
                                        <span class="clubworx-pricing-cycle">/<?php echo esc_html($plan['billing_cycle']); ?></span>
                                    </div>
                                    
                                    <?php if (!empty($plan['description'])): ?>
                                        <p class="clubworx-pricing-description"><?php echo esc_html($plan['description']); ?></p>
                                    <?php endif; ?>
                                    
                                    <a href="<?php echo esc_url($atts['button_link']); ?>" class="clubworx-pricing-button">
                                        <?php echo esc_html($atts['button_text']); ?>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
            
            <!-- Show any remaining categories not in the main order -->
            <?php foreach ($grouped_plans as $category => $category_plans): ?>
                <?php if (!in_array($category, $category_order) && !empty($category_plans)): ?>
                    <div class="clubworx-pricing-category">
                        <h4 class="clubworx-pricing-category-title"><?php echo esc_html($category_labels[$category]); ?></h4>
                        <div class="clubworx-pricing-cards">
                            <?php foreach ($category_plans as $plan): ?>
                                <?php 
                                $is_highlighted = in_array($plan['name'], $highlight_plans) || 
                                                stripos($plan['name'], 'unlimited') !== false;
                                $card_class = 'clubworx-pricing-card';
                                if ($is_highlighted) {
                                    $card_class .= ' clubworx-pricing-card-highlighted';
                                }
                                ?>
                                <div class="<?php echo esc_attr($card_class); ?>">
                                    <?php if ($is_highlighted): ?>
                                        <div class="clubworx-pricing-badge">Popular</div>
                                    <?php endif; ?>
                                    
                                    <h4 class="clubworx-pricing-plan-name"><?php echo esc_html($plan['name']); ?></h4>
                                    
                                    <div class="clubworx-pricing-price">
                                        <span class="clubworx-pricing-currency"><?php echo esc_html($plan['currency']); ?></span>
                                        <span class="clubworx-pricing-amount"><?php echo number_format($plan['price'], 0); ?></span>
                                        <span class="clubworx-pricing-cycle">/<?php echo esc_html($plan['billing_cycle']); ?></span>
                                    </div>
                                    
                                    <?php if (!empty($plan['description'])): ?>
                                        <p class="clubworx-pricing-description"><?php echo esc_html($plan['description']); ?></p>
                                    <?php endif; ?>
                                    
                                    <a href="<?php echo esc_url($atts['button_link']); ?>" class="clubworx-pricing-button">
                                        <?php echo esc_html($atts['button_text']); ?>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
            
            <!-- Mobile scroll hint -->
            <div class="clubworx-pricing-scroll-hint"></div>
        </div>
        <?php
    }
    
    /**
     * Get classes for a specific day
     */
    private function get_day_classes($schedule, $day, $atts) {
        $classes = array();
        
        // Collect all classes for this day
        $all_classes = array();
        
        if (isset($schedule['kids']['under6'][$day])) {
            foreach ($schedule['kids']['under6'][$day] as $class) {
                $all_classes[] = array(
                    'name' => $this->extract_class_name($class),
                    'time' => $this->extract_class_time($class),
                    'category' => 'Kids (Under 6)',
                    'category_class' => 'kids-under6',
                    'sort_time' => $this->time_to_minutes($this->extract_class_time($class))
                );
            }
        }
        
        if (isset($schedule['kids']['over6'][$day])) {
            foreach ($schedule['kids']['over6'][$day] as $class) {
                $all_classes[] = array(
                    'name' => $this->extract_class_name($class),
                    'time' => $this->extract_class_time($class),
                    'category' => 'Kids (6+)',
                    'category_class' => 'kids-over6',
                    'sort_time' => $this->time_to_minutes($this->extract_class_time($class))
                );
            }
        }
        
        if (isset($schedule['adults']['general'][$day])) {
            foreach ($schedule['adults']['general'][$day] as $class) {
                $all_classes[] = array(
                    'name' => $this->extract_class_name($class),
                    'time' => $this->extract_class_time($class),
                    'category' => 'Adults General',
                    'category_class' => 'adults-general',
                    'sort_time' => $this->time_to_minutes($this->extract_class_time($class))
                );
            }
        }
        
        if (isset($schedule['adults']['foundations'][$day])) {
            foreach ($schedule['adults']['foundations'][$day] as $class) {
                $all_classes[] = array(
                    'name' => $this->extract_class_name($class),
                    'time' => $this->extract_class_time($class),
                    'category' => 'Adults Foundations',
                    'category_class' => 'adults-foundations',
                    'sort_time' => $this->time_to_minutes($this->extract_class_time($class))
                );
            }
        }
        
        if (isset($schedule['women'][$day])) {
            foreach ($schedule['women'][$day] as $class) {
                $all_classes[] = array(
                    'name' => $this->extract_class_name($class),
                    'time' => $this->extract_class_time($class),
                    'category' => 'Women',
                    'category_class' => 'women',
                    'sort_time' => $this->time_to_minutes($this->extract_class_time($class))
                );
            }
        }
        
        // Sort by time
        usort($all_classes, function($a, $b) {
            return $a['sort_time'] - $b['sort_time'];
        });
        
        // Filter by categories if specified
        if ($atts['categories'] !== 'all') {
            $allowed_categories = array_map('trim', explode(',', $atts['categories']));
            $all_classes = array_filter($all_classes, function($class) use ($allowed_categories) {
                return in_array($class['category_class'], $allowed_categories);
            });
        }
        return $all_classes;
    }
    
    /**
     * Extract class name from full class string
     */
    private function extract_class_name($classString) {
        // Extract name from strings like "General Gi Class - 6:00 PM"
        if (preg_match('/^(.+?) - \d{1,2}:\d{2} [AP]M$/', $classString, $matches)) {
            return trim($matches[1]);
        }
        return $classString;
    }
    
    /**
     * Extract class time from full class string
     */
    private function extract_class_time($classString) {
        // Extract time from strings like "General Gi Class - 6:00 PM"
        if (preg_match('/- (\d{1,2}:\d{2} [AP]M)$/', $classString, $matches)) {
            return $matches[1];
        }
        return '';
    }
    
    /**
     * Get booking details by ID
     */
    public function get_booking_details($request) {
        $booking_id = $request->get_param('id');
        
        if (empty($booking_id)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Booking ID is required'
            ), 400);
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'clubworx_bookings';
        
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $booking_id
        ));
        
        if (!$booking) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Booking not found'
            ), 404);
        }
        
        // Parse JSON data
        $booking->request_data = json_decode($booking->request_data, true);
        $booking->response_data = json_decode($booking->response_data, true);
        
        return new WP_REST_Response(array(
            'success' => true,
            'booking' => $booking
        ), 200);
    }
    
    /**
     * Get membership plans from ClubWorx (supports multiple accounts via ?account=).
     */
    public function get_membership_plans($request) {
        $refresh = $request->get_param('refresh');
        $account_raw = $request->get_param('account');
        $account_raw = is_string($account_raw) ? $account_raw : '';
        $slugs = Clubworx_Locations::expand_account_slugs($account_raw, null);

        $aggregate_cache_key = 'clubworx_membership_plans_agg_' . md5(implode(',', $slugs));
        if (!$refresh) {
            $cached_agg = get_transient($aggregate_cache_key);
            if ($cached_agg !== false) {
                return new WP_REST_Response(array(
                    'success' => true,
                    'plans' => $cached_agg,
                    'cached' => true,
                    'accounts' => $slugs,
                ), 200);
            }
        }

        $all_plans = array();

        foreach ($slugs as $slug) {
            $loc = Clubworx_Locations::get($slug);
            if (!$loc || empty($loc['api_url']) || empty($loc['api_key'])) {
                continue;
            }

            $single_cache_key = 'clubworx_membership_plans_' . sanitize_key($slug);
            $plans = false;
            if (!$refresh) {
                $plans = get_transient($single_cache_key);
            }

            if ($plans === false || !is_array($plans)) {
                $base_url = rtrim($loc['api_url'], '/');
                if (!str_ends_with($base_url, '/api/v2')) {
                    $base_url .= '/api/v2';
                }
                $api_url = $base_url . '/membership_plans?account_key=' . urlencode($loc['api_key']);

                $response = wp_remote_get($api_url, array(
                    'headers' => array(
                        'Accept' => 'application/json',
                    ),
                    'timeout' => 30,
                ));

                if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                    continue;
                }

                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                if (!is_array($data)) {
                    continue;
                }

                $plans = $this->parse_membership_plans($data);
                if (!is_array($plans)) {
                    $plans = array();
                }

                set_transient($single_cache_key, $plans, 12 * HOUR_IN_SECONDS);
            }

            $label = isset($loc['label']) ? $loc['label'] : $slug;
            foreach ($plans as $p) {
                if (!is_array($p)) {
                    continue;
                }
                $p['location_slug'] = $slug;
                $p['location_label'] = $label;
                $all_plans[] = $p;
            }
        }

        if (empty($all_plans)) {
            $all_plans = $this->get_fallback_plans();
        }

        set_transient($aggregate_cache_key, $all_plans, 12 * HOUR_IN_SECONDS);

        return new WP_REST_Response(array(
            'success' => true,
            'plans' => $all_plans,
            'cached' => false,
            'accounts' => $slugs,
        ), 200);
    }
    
    /**
     * Parse membership plans from ClubWorx response
     */
    private function parse_membership_plans($data) {
        $plans = array();
        
        // Handle different response structures
        $plans_data = array();
        if (is_array($data)) {
            if (isset($data['plans']) && is_array($data['plans'])) {
                $plans_data = $data['plans'];
            } elseif (isset($data['membership_plans']) && is_array($data['membership_plans'])) {
                $plans_data = $data['membership_plans'];
            } else {
                $plans_data = $data; // Assume data is array of plans
            }
        }
        
        error_log('Clubworx Integration: Processing ' . count($plans_data) . ' membership plans');
        
        foreach ($plans_data as $plan) {
            // Extract basic info - ClubWorx uses different field names
            $name = isset($plan['name']) ? $plan['name'] : '';
            
            // Try to get price from recurring_payment_amount first, then upfront_payment_amount
            $price = 0;
            if (isset($plan['recurring_payment_amount']) && $plan['recurring_payment_amount'] > 0) {
                $price = floatval($plan['recurring_payment_amount']);
            } elseif (isset($plan['upfront_payment_amount']) && $plan['upfront_payment_amount'] > 0) {
                $price = floatval($plan['upfront_payment_amount']);
            }
            
            $currency = 'AUD'; // ClubWorx doesn't provide currency, assume AUD
            $billing_cycle = 'weekly'; // Default to weekly
            
            // Extract billing cycle from recurring_payment_frequency
            if (isset($plan['recurring_payment_frequency'])) {
                $frequency = strtolower($plan['recurring_payment_frequency']);
                if (strpos($frequency, 'week') !== false) {
                    $billing_cycle = 'weekly';
                } elseif (strpos($frequency, 'month') !== false) {
                    $billing_cycle = 'monthly';
                } elseif (strpos($frequency, 'year') !== false) {
                    $billing_cycle = 'yearly';
                }
            }
            
            // Skip plans without clear pricing or with zero/null prices
            if (empty($name) || $price <= 0) {
                continue;
            }
            
            // Determine category from name
            $category = $this->determine_plan_category($name);
            
            $plans[] = array(
                'id' => isset($plan['id']) ? $plan['id'] : '',
                'name' => $name,
                'price' => floatval($price),
                'currency' => $currency,
                'billing_cycle' => $billing_cycle,
                'category' => $category,
                'description' => isset($plan['description']) ? $plan['description'] : '',
                'features' => isset($plan['features']) ? $plan['features'] : array()
            );
        }
        
        error_log('Clubworx Integration: Parsed ' . count($plans) . ' valid membership plans');
        return $plans;
    }
    
    /**
     * Determine plan category from name
     */
    private function determine_plan_category($name) {
        $name_lower = strtolower($name);
        
        // Check for family plans first
        if (strpos($name_lower, 'family') !== false || strpos($name_lower, 'partner') !== false) {
            return 'family';
        } elseif (strpos($name_lower, 'kids') !== false || strpos($name_lower, 'child') !== false || strpos($name_lower, 'junior') !== false) {
            return 'kids';
        } elseif (strpos($name_lower, 'women') !== false || strpos($name_lower, 'female') !== false || strpos($name_lower, 'ladies') !== false) {
            return 'women';
        } elseif (strpos($name_lower, 'adult') !== false || strpos($name_lower, 'senior') !== false) {
            return 'adults';
        }
        
        return 'general';
    }
    
    /**
     * Get fallback plans when API is unavailable
     */
    private function get_fallback_plans() {
        // Allow themes/plugins to provide fallback plans
        $fallback_plans = apply_filters('clubworx_pricing_fallback_plans', array());
        
        if (!empty($fallback_plans)) {
            return $fallback_plans;
        }
        
        // Default fallback plans
        return array(
            array(
                'id' => 'fallback-1',
                'name' => 'Adults General',
                'price' => 120.00,
                'currency' => 'AUD',
                'billing_cycle' => 'monthly',
                'category' => 'adults',
                'description' => 'Unlimited classes for adults',
                'features' => array()
            ),
            array(
                'id' => 'fallback-2',
                'name' => 'Kids Under 6',
                'price' => 80.00,
                'currency' => 'AUD',
                'billing_cycle' => 'monthly',
                'category' => 'kids',
                'description' => 'Classes for children under 6',
                'features' => array()
            ),
            array(
                'id' => 'fallback-3',
                'name' => 'Kids Over 6',
                'price' => 100.00,
                'currency' => 'AUD',
                'billing_cycle' => 'monthly',
                'category' => 'kids',
                'description' => 'Classes for children over 6',
                'features' => array()
            )
        );
    }
}

