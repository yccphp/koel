<?php

namespace E2E;

use App\Application;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

abstract class TestCase extends \PHPUnit_Framework_TestCase
{
    use WebDriverShortcuts;

    /**
     * @var Application
     */
    protected $app;

    /**
     * ID of the current screen wrapper (with leading #)
     *
     * @var string
     */
    public $wrapperId;

    /**
     * The default Koel URL for E2E (server by `php artisan serve --port=8081`).
     *
     * @var string
     */
    protected $url = 'http://localhost:8081';
    protected $coverPath;

    /**
     * TestCase constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->createApp();
        $this->prepareForE2E();
        $this->driver = RemoteWebDriver::create('http://localhost:4444/wd/hub', DesiredCapabilities::chrome());
    }

    /**
     * @return Application
     */
    protected function createApp()
    {
        $this->app = require __DIR__.'/../../bootstrap/app.php';
        $this->app->make(Kernel::class)->bootstrap();

        return $this->app;
    }

    private function prepareForE2E()
    {
        // Make sure we have a fresh database.
        @unlink(__DIR__.'/../../database/e2e.sqlite');
        touch(__DIR__.'/../../database/e2e.sqlite');

        Artisan::call('migrate');
        Artisan::call('db:seed');
        Artisan::call('db:seed', ['--class' => 'E2EDataSeeder']);

        if (!file_exists($this->coverPath)) {
            @mkdir($this->coverPath, 0777, true);
        }
    }

    /**
     * Log into Koel.
     *
     * @param string $username
     * @param string $password
     *
     * @return $this
     */
    protected function login($username = 'koel@example.com', $password = 'SoSecureK0el')
    {
        $this->typeIn("#app > div.login-wrapper > form > [type='email']", $username);
        $this->typeIn("#app > div.login-wrapper > form > [type='password']", $password);
        $this->enter();

        return $this;
    }

    protected function loginAndWait()
    {
        $this->login();
        $this->waitUntilTextSeenIn('Koel Admin', '#userBadge > a.view-profile.control > span');

        return $this;
    }

    /**
     * A helper to allow going to a specific screen.
     *
     * @param $screen
     *
     * @return $this
     *
     * @throws \Exception
     */
    protected function goto($screen)
    {
        $this->wrapperId = "#{$screen}Wrapper";

        if ($screen === 'favorites') {
            $this->click('#sidebar .favorites a');
        } else {
            $this->click("#sidebar a.$screen");
        }

        $this->waitUntilSeen($this->wrapperId);

        return $this;
    }

    protected function loginAndGoTo($screen)
    {
        return $this->loginAndWait()->goto($screen);
    }

    protected function waitForUserInput()
    {
        if (trim(fgets(fopen('php://stdin', 'rb'))) !== chr(13)) {
            return;
        }
    }

    protected function focusIntoApp()
    {
        $this->click('#app');
    }

    public function setUp()
    {
        $this->driver->get($this->url);
    }

    public function tearDown()
    {
        $this->driver->quit();
    }
}