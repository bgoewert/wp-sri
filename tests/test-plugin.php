<?php // phpcs:ignoreFile
/**
 * Test cases for the WP-SRI plugin.
 *
 * @package plugin
 */

class PluginTest extends WP_UnitTestCase {

	/**
	 * @var WP_SRI_Plugin
	 */
    protected $plugin;

	/**
	 * @var array
	 */
    protected $excluded;

    public function setUp(): void {
        parent::setUp();
        $this->plugin = new WP_SRI_Plugin();
        $this->excluded = get_option( WP_SRI_Plugin::$prefix . 'excluded_hashes', array() );
    }

    public function testLocalResourceIsSuccessfullyDetected() {
        $url = trailingslashit(get_site_url()) . '/example.js';
        $this->assertTrue( $this->plugin->is_local_resource($url) );
    }

    public function testRemoteResourceIsSuccessfullyDetected() {
        $url = 'https://cdn.datatables.net/1.10.7/js/jquery.dataTables.min.js';
        $this->assertFalse( $this->plugin->is_local_resource($url) );
    }

    public function testHashResource() {
        $content = 'alert("Hello, world!");';
        $expected_hash = 'niqXkYYIkmWt0jYVFjVzcI+Q5nc3jzIdmbLXJqKD5A8=';
        $encoded_hash = $this->plugin->hash_resource($content);
        $this->assertEquals( $expected_hash, $encoded_hash );
    }

    public function testDeleteKnownHash() {
        update_option(WP_SRI_Plugin::$prefix . 'known_hashes', array(
            'https://cdn.datatables.net/1.10.6/js/jquery.dataTables.min.js' => 'JOLmOuOEVbUWcM57vmy0F48W/2S7UCJB3USm7/Tu10U='
        ));
        $remaining_known_hashes = array();
        $this->plugin->delete_known_hash('https://cdn.datatables.net/1.10.6/js/jquery.dataTables.min.js');
        $this->assertEquals($remaining_known_hashes, get_option(WP_SRI_Plugin::$prefix . 'known_hashes'));
    }

    public function testFilterLinkTag() {
        // TODO: write a test with mock HTTP responses?
        $this->markTestSkipped();
    }

    public function testUpdateExcludedUrl() {
        $url = 'https://fonts.googleapis.com/css?family=Lato%3A300%2C400%2C700&ver=1.0.0';

        $this->assertCount( 2, $this->excluded );
        $this->assertFalse( array_search( esc_url_raw( $url ), $this->excluded ) );
        $this->plugin->update_excluded_url( $url, true );
        $this->excluded = get_option( WP_SRI_Plugin::$prefix . 'excluded_hashes', array() );
        $this->assertTrue( false !== array_search( esc_url_raw( $url ), $this->excluded ) );
    }

    public function testProcessActions() {
        $url = 'https://cdn.datatables.net/1.10.7/js/jquery.dataTables.min.js';

        // Check that this URL is not already excluded.
        $this->assertFalse( array_search( esc_url_raw( $url ), $this->excluded ) );

        // Set up our $_GET vars to exclude.
        $_GET['action'] = 'exclude';
        $_GET['url'] = esc_url_raw( $url );
        $_GET['_' . WP_SRI_Plugin::$prefix . 'nonce']  = wp_create_nonce( 'update_sri_hash' );

        // Process the exclude action.
        $this->plugin->process_actions();

        // Grab our updated exclude array.
        $this->excluded = get_option( WP_SRI_Plugin::$prefix . 'excluded_hashes', array() );

        // The plugin added our script and stylesheet so this should the 3rd.
        $this->assertCount( 3, $this->excluded );
        $this->assertEquals( 2, array_search( esc_url_raw( $url ), $this->excluded ) );

        // Set up our $_GET vars to include.
        $_GET['action'] = 'include';
        $_GET['url'] = esc_url_raw( $url );
        $_GET['_' . WP_SRI_Plugin::$prefix . 'nonce']  = wp_create_nonce( 'update_sri_hash' );

        // Process the include action.
        $this->plugin->process_actions();

        // Grab our updated exclude array.
        $this->excluded = get_option( WP_SRI_Plugin::$prefix . 'excluded_hashes', array() );

        // Our array count should be one fewer now.
        $this->assertCount( 2, $this->excluded );
        // URL should no longer be found the array.
        $this->assertEquals( false, array_search( esc_url_raw( $url ), $this->excluded ) );
    }

}
