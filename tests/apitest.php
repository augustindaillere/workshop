<?php

use PHPUnit\Framework\TestCase;

/**
 * Teste les endpoints de api/ en simulant des requêtes HTTP.
 * Chaque endpoint tourne dans son propre processus PHP (ils utilisent exit/sendJson).
 * On ne teste ici que les cas qui échouent AVANT l'accès à la base de données,
 * pour ne jamais toucher à la vraie base SQLite.
 */
final class ApiTest extends TestCase
{
    /** Image PNG 1x1 valide. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private function call(string $file, string $method = 'POST', array $data = []): array
    {
        $cmd = sprintf(
            '%s %s %s %s %s 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(__DIR__ . '/support/run_endpoint.php'),
            escapeshellarg($file),
            escapeshellarg($method),
            escapeshellarg(json_encode($data))
        );
        $output = shell_exec($cmd) ?? '';
        $json = json_decode($output, true);
        $this->assertIsArray($json, "Réponse non JSON pour $file : $output");
        return $json;
    }

    private function validScore(array $override = []): array
    {
        return array_merge([
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'final_time' => '01:30:250',
            'stopped_at' => 3,
            'photo' => 'data:image/png;base64,' . self::PNG,
        ], $override);
    }

    // ───────────── start_game.php ─────────────
    public function testStartGameReturnsSuccess(): void
    {
        $this->assertTrue($this->call('start_game.php', 'POST')['success']);
    }

    // ───────────── get_leaderboard.php ─────────────
    public function testLeaderboardRejectsPost(): void
    {
        $res = $this->call('get_leaderboard.php', 'POST');
        $this->assertFalse($res['success']);
        $this->assertSame('Méthode non autorisée.', $res['error']);
    }

    // ───────────── submit_score.php ─────────────
    public function testSubmitScoreRejectsGet(): void
    {
        $this->assertSame('Méthode non autorisée.', $this->call('submit_score.php', 'GET')['error']);
    }

    public function testSubmitScoreRequiresPhoto(): void
    {
        $res = $this->call('submit_score.php', 'POST', $this->validScore(['photo' => '']));
        $this->assertSame('Une photo est obligatoire.', $res['error']);
    }

    public function testSubmitScoreRequiresNames(): void
    {
        $res = $this->call('submit_score.php', 'POST', $this->validScore(['first_name' => '  ']));
        $this->assertSame('Tous les champs sont obligatoires.', $res['error']);
    }

    public function testSubmitScoreRejectsTooLongName(): void
    {
        $res = $this->call('submit_score.php', 'POST', $this->validScore(['last_name' => str_repeat('a', 81)]));
        $this->assertSame('Le nom ou le prénom est trop long.', $res['error']);
    }

    /** @dataProvider invalidStages */
    public function testSubmitScoreRejectsInvalidStage(int $stage): void
    {
        $res = $this->call('submit_score.php', 'POST', $this->validScore(['stopped_at' => $stage]));
        $this->assertSame('L’étape doit être comprise entre 1 et 5.', $res['error']);
    }

    public static function invalidStages(): array
    {
        return [[0], [6]];
    }

    /** @dataProvider invalidTimes */
    public function testSubmitScoreRejectsInvalidTimeFormat(string $time): void
    {
        $res = $this->call('submit_score.php', 'POST', $this->validScore(['final_time' => $time]));
        $this->assertSame('Le temps doit respecter le format MM:SS:mmm.', $res['error']);
    }

    public static function invalidTimes(): array
    {
        return [['1:2:3'], ['00:75:000'], ['abc'], ['01:30']];
    }

    public function testSubmitScoreRejectsPhotoWithoutImageFormat(): void
    {
        $res = $this->call('submit_score.php', 'POST', $this->validScore(['photo' => 'https://exemple.fr/p.png']));
        $this->assertSame('La photo doit être envoyée au format image.', $res['error']);
    }

    public function testSubmitScoreRejectsFakeImage(): void
    {
        $fake = 'data:image/png;base64,' . base64_encode('ceci n\'est pas une image');
        $res = $this->call('submit_score.php', 'POST', $this->validScore(['photo' => $fake]));
        $this->assertFalse($res['success']);
        $this->assertSame('La photo est trop volumineuse.', $res['error']);
    }

    // ───────────── upload_photo.php ─────────────
    public function testUploadPhotoRejectsGet(): void
    {
        $this->assertSame('Méthode non autorisée.', $this->call('upload_photo.php', 'GET')['error']);
    }

    /** @dataProvider invalidPages */
    public function testUploadPhotoRejectsInvalidPage(int $page): void
    {
        $res = $this->call('upload_photo.php', 'POST', ['page' => $page, 'image' => 'data:image/png;base64,' . self::PNG]);
        $this->assertSame('Page ou image invalide.', $res['error']);
    }

    public static function invalidPages(): array
    {
        return [[0], [7]];
    }

    public function testUploadPhotoRejectsUnsupportedFormat(): void
    {
        $res = $this->call('upload_photo.php', 'POST', ['page' => 1, 'image' => 'data:image/gif;base64,R0lGODlhAQABAAAAACw=']);
        $this->assertSame('Format d’image non pris en charge.', $res['error']);
    }

    public function testUploadPhotoRejectsFakeImage(): void
    {
        $fake = 'data:image/png;base64,' . base64_encode('texte');
        $res = $this->call('upload_photo.php', 'POST', ['page' => 2, 'image' => $fake]);
        $this->assertSame('Le fichier envoyé n’est pas une image valide.', $res['error']);
    }

    public function testUploadPhotoAcceptsValidImage(): void
    {
        $res = $this->call('upload_photo.php', 'POST', ['page' => 3, 'image' => 'data:image/png;base64,' . self::PNG]);
        $this->assertTrue($res['success']);
        $this->assertSame(3, $res['page']);
        $this->assertStringStartsWith('data:image/png;base64,', $res['path']);
    }

    // ───────────── leave_site.php ─────────────
    public function testLeaveSiteRejectsGet(): void
    {
        $this->assertSame('Méthode non autorisée.', $this->call('leave_site.php', 'GET')['error']);
    }

    public function testLeaveSiteRejectsInvalidData(): void
    {
        $res = $this->call('leave_site.php', 'POST', ['first_name' => '', 'last_name' => 'X']);
        $this->assertSame('Données de sortie invalides.', $res['error']);
    }

    public function testLeaveSiteRejectsOutOfRangeStage(): void
    {
        $res = $this->call('leave_site.php', 'POST', [
            'first_name' => 'Ada', 'last_name' => 'L', 'final_time_ms' => 1000, 'stopped_at' => 9,
        ]);
        $this->assertSame('Données de sortie invalides.', $res['error']);
    }
}