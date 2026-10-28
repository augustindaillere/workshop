<?php

use PHPUnit\Framework\TestCase;

/**
 * Vérifie que les pages PHP du site existent et n'ont pas d'erreur de syntaxe.
 */
final class PagesTest extends TestCase
{
    private const ROOT_PAGES = [
        'index.php', 'page2.php', 'page3.php', 'page4.php', 'page5.php',
        'fin.php', 'leaderboard.php',
        'api/get_leaderboard.php', 'api/leave_site.php', 'api/start_game.php',
        'api/submit_score.php', 'api/upload_photo.php',
    ];

    private static function root(string $file): string
    {
        return __DIR__ . '/../' . $file;
    }

    /** @dataProvider pages */
    public function testPageExists(string $page): void
    {
        $this->assertFileExists(self::root($page));
    }

    /** @dataProvider pages */
    public function testPageHasNoSyntaxError(string $page): void
    {
        $cmd = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg(self::root($page)) . ' 2>&1';
        exec($cmd, $output, $code);
        $this->assertSame(0, $code, implode("\n", $output));
    }

    public static function pages(): array
    {
        return array_map(fn ($p) => [$p], self::ROOT_PAGES);
    }

    public function testLeaderboardIncludesExistAndAreReferenced(): void
    {
        $content = file_get_contents(self::root('leaderboard.php'));

        $this->assertStringContainsString("'header.php'", $content);
        $this->assertStringContainsString("'footer.php'", $content);
        $this->assertStringContainsString('assets/js/leaderboard.js', $content);

        $this->assertFileExists(self::root('includes/header.php'));
        $this->assertFileExists(self::root('includes/footer.php'));
        $this->assertFileExists(self::root('assets/js/leaderboard.js'));
    }

    public function testLeaderboardContainsTheTableUsedByTheJavaScript(): void
    {
        $content = file_get_contents(self::root('leaderboard.php'));
        foreach (['id="classement"', 'id="classement-corps"', 'id="nb-joueurs"', 'id="etat-chargement"'] as $id) {
            $this->assertStringContainsString($id, $content);
        }
    }
}