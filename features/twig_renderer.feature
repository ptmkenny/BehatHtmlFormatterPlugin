  Feature: Twig Renderer

  Background:
    Given a file named "behat.php" with:
      """
      <?php
      use Behat\Config\Config;
      use Behat\Config\Extension;
      use Behat\Config\Formatter\Formatter;
      use Behat\Config\Profile;
      use Behat\Config\Suite;
      use emuse\BehatHTMLFormatter\BehatHTMLFormatterExtension;

      return (new Config())
          ->withProfile((new Profile('default'))
              ->withFormatter((new Formatter('html'))->withOutputPath(__DIR__.'/build'))
              ->withExtension(new Extension(BehatHTMLFormatterExtension::class, [
                  'name' => 'html',
                  'renderer' => 'Twig',
                  'file_name' => 'Index',
                  'print_args' => true,
                  'print_outp' => true,
                  'loop_break' => true,
              ]))
              ->withSuite((new Suite('suite1'))->withPaths(__DIR__.'/features/suite1'))
              ->withSuite((new Suite('suite2'))->withPaths(__DIR__.'/features/suite2'))
              ->withSuite((new Suite('suite3'))->withPaths(__DIR__.'/features/suite3')));
      """
    Given a file named "features/bootstrap/FeatureContext.php" with:
      """
      <?php
      use Behat\Behat\Context\Context;
      use Behat\Behat\Tester\Exception\PendingException;
      use Behat\Step\When;

      class FeatureContext implements Context
      {
          #[When('I give a passing step')]
          public function passingStep(): void
          {
          }

          #[When('I give a failing step')]
          public function failingStep(): void
          {
              throw new RuntimeException('Failing step');
          }

          #[When('I give a pending step')]
          public function pendingStep(): void
          {
              throw new PendingException();
          }
      }
      """

 Scenario: Multiple Suites with multiple results
    Given a file named "features/suite1/suite_failing_with_passing.feature" with:
      """
      Feature: Suite failing with passing scenarios
        Scenario: Passing scenario
          Then I give a passing step
        Scenario: One Failing step
          Then I give a failing step
        Scenario: One Pending step
          Then I give a pending step
        Scenario: Passing and Pending steps
          Then I give a passing step
          Then I give a pending step
        Scenario: Passing and Failing steps
          Then I give a passing step
          Then I give a failing step
      """
    Given a file named "features/suite2/suite_passing.feature" with:
      """
      Feature: Suite passing
        Scenario: Passing scenario
          Then I give a passing step
      """
    Given a file named "features/suite3/suite_pending.feature" with:
      """
      Feature: Suite with pending scenario
        Scenario: One pending step
          Then I give a pending step
        Scenario: One undefined step
          Then I give an undefined step
      """
    When I run "behat --no-colors --no-interaction"
    Then it should fail with:
      """
      --- Use --snippets-for CLI option to generate snippets for following suite3 suite steps:

          Then I give an undefined step
      """
    And report file should exist
    # Chart data: features [failed, passed], scenarios [failed, pending, passed],
    # steps [failed, passed]. The formatter counts an undefined scenario as failed
    # and leaves pending and undefined steps out of the step chart.
    And report file should contain:
      """
      3 Features:
      """
    And report file should contain:
      """
      data: [2, 1],
      """
    And report file should contain:
      """
      data: [3, 3, 2],
      """
    And report file should contain:
      """
      data: [2, 4],
      """

