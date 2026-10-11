<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The deploy script cannot be run here (it works only inside /opt/helpdesk), so these keep its order: everything that
 * needs the network or a long build happens before the site goes into maintenance mode, and a failure there changes
 * nothing on the server.
 */
class DeployScriptTest extends TestCase
{
    private string $flow;

    private string $script;

    protected function setUp(): void
    {
        parent::setUp();

        $this->script = (string) file_get_contents(dirname(__DIR__, 2).'/scripts/deploy-helpdesk-remote.sh');
        // The main flow starts after the helper functions, which mention some of the same commands.
        $this->flow = substr($this->script, (int) strpos($this->script, 'trap rollback_and_exit ERR'));
    }

    public function test_the_package_audit_and_the_front_end_build_run_before_maintenance_mode(): void
    {
        $down = strpos($this->flow, 'php artisan down');

        $this->assertNotFalse($down);
        $this->assertLessThan($down, strpos($this->flow, 'audit --locked'));
        $this->assertLessThan($down, strpos($this->flow, 'npm run build'));
        $this->assertLessThan($down, strpos($this->flow, 'git archive'));
    }

    public function test_it_deploys_exactly_the_commit_it_audited_and_built(): void
    {
        $this->assertStringContainsString('git merge --ff-only "$NEW_HEAD"', $this->flow);
        $this->assertStringNotContainsString('git pull', $this->flow);
    }

    public function test_it_does_not_need_tinker_to_read_the_migration_batch(): void
    {
        $this->assertStringNotContainsString('tinker', $this->script);
        $this->assertStringContainsString('helpdesk:migration-batch', $this->script);
    }

    public function test_a_failure_before_anything_changed_leaves_the_site_up(): void
    {
        $this->assertStringContainsString('LIVE_TREE_TOUCHED=1', $this->flow);
        $this->assertLessThan(
            strpos($this->flow, 'php artisan down'),
            strpos($this->flow, 'LIVE_TREE_TOUCHED=1')
        );
    }
}
