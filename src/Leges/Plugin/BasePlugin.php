<?php

namespace OWC\Leges\Plugin;

use Exception;
use OWC\Leges\Config;

abstract class BasePlugin
{

	/**
	 * Path to the root of the plugin.
	 *
	 * @var string
	 */
	protected $rootPath;

	/**
	 * Instance of the configuration repository.
	 *
	 * @var \OWC\Leges\Config
	 */
	public $config;

	/**
	 * Instance of the hook loader.
	 */
	public $loader;

	/**
	 * Creates the base plugin functionality.
	 *
	 * Create startup hooks and tear down hooks.
	 * Boot up admin and frontend functionality.
	 * Register the actions and filters from the loader.
	 *
	 * @param string $rootPath
	 *
	 * @throws Exception
	 */
	public function __construct($rootPath)
	{
		$this->rootPath = $rootPath;

		$this->config = new Config($this->rootPath . '/config');
		$this->config->boot();

		$this->loader = Loader::getInstance();

		$this->bootServiceProviders('register');

		$this->bootLanguages();

		if ( is_network_admin() ) {
			$this->bootServiceProviders('register', 'network');
		}

		$this->bootServiceProviders('register', is_admin() ? 'admin' : 'frontend');

		$this->bootServiceProviders('boot');
		if ( is_network_admin() ) {
			$this->bootServiceProviders('boot', 'network');
		}

		$this->bootServiceProviders('boot', is_admin() ? 'admin' : 'frontend');

		$this->loader->register();
	}

	/**
	 * Boot service providers
	 *
	 * @param string $method
	 * @param string $location
	 *
	 * @throws Exception
	 */
	private function bootServiceProviders($method = '', $location = '')
	{
		$suffix   = $location ? '.' . $location : '';
		$services = $this->config->get('core.providers' . $suffix);

		foreach ( $services as $service ) {
			// Only boot global service providers here.
			if ( is_array($service) ) {
				continue;
			}

			$service = new $service($this);

			if ( ! $service instanceof ServiceProvider ) {
				throw new Exception('Provider must extend ServiceProvider.');
			}

			if ( method_exists($service, $method) ) {
				$service->$method();
			}
		}
	}

	/**
	 * Startup hooks to initialize the plugin.
	 *
	 * @param $file
	 */
	public static function addStartUpHooks($file)
	{
		/**
		 * This hook registers a plugin function to be run when the plugin is activated.
		 */
		register_activation_hook($file, [
			'\OWC\Leges\Hooks',
			'pluginActivation'
		]);

		add_action('admin_notices', function() {
			if ( get_transient('owc-leges-plugin-actions-notice') ) {
				if ( ! is_plugin_active('shortcode-ui/shortcode-ui.php') ) { ?>
					<div class="updated notice is-dismissible">
						<p>For a nicer integration, the plugin <a href="https://wordpress.org/plugins/shortcode-ui/">Shortcake (Shortcode
								UI)</a> is recommended!</a></p>
					</div>
					<?php delete_transient('owc-leges-plugin-actions-notice');
				}
			}
		});

		/**
		 * This hook is run immediately after any plugin is activated, and may be used to detect the activation of plugins.
		 * If a plugin is silently activated (such as during an update), this hook does not fire.
		 */
		add_action('activated_plugin', [
			'\OWC\Leges\Hooks',
			'pluginActivated'
		], 10, 2);
	}

	/**
	 * Teardown hooks to cleanup or uninstall the plugin.
	 *
	 * @param $file
	 */
	public static function addTearDownHooks($file)
	{
		/**
		 * This hook registers a plugin function to be run when the plugin is deactivated.
		 */
		register_deactivation_hook($file, [
			'\OWC\Leges\Hooks',
			'pluginDeactivation'
		]);

		/**
		 * This hook is run immediately after any plugin is deactivated, and may be used to detect the deactivation of other plugins.
		 */
		add_action('deactivated_plugin', ['\OWC\Leges\Hooks', 'pluginDeactivated'], 10, 2);

		/**
		 * Registers the uninstall hook that will be called when the user clicks on the uninstall link that calls for the plugin to uninstall itself.
		 * The link won’t be active unless the plugin hooks into the action.
		 */
		register_uninstall_hook($file, [
			'\OWC\Leges\Hooks',
			'uninstallPlugin'
		]);
	}

	/**
	 * Get the name of the plugin.
	 *
	 * @return string
	 */
	public function getName()
	{
		return static::NAME;
	}

	/**
	 * Get the version of the plugin.
	 *
	 * @return string
	 */
	public function getVersion()
	{
		return static::VERSION;
	}

	/**
	 * Add language file.
	 */
	private function bootLanguages()
	{
		load_plugin_textdomain(
			'owc-leges',
			false,
			dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
		);
	}

	/**
	 * Return root path of plugin.
	 *
	 * @return string
	 */
	public function getRootPath()
	{
		return $this->rootPath;
	}

}