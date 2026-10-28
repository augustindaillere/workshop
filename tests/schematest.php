<?php

use PHPUnit\Framework\TestCase;

/**
 * Teste schemas.sql (table "scores" du leaderboard) sur une base SQLite en mémoire.
 */
final class SchemaTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(file_get_contents(__DIR__ . '/../schemas.sql'));
    }

    private function insert(array $o = []): void
    {
        $d = array_merge([
            'photo' => 'p.jpg', 'first_name' => 'Ada', 'last_name' => 'Lovelace',
            'final_time' => '00:01:00', 'final_time_ms' => 60000, 'stopped_at' => 3,
        ], $o);
        $this->pdo->prepare(
            'INSERT INTO scores (photo, first_name, last_name, final_time, final_time_ms, stopped_at)
             VALUES (:photo, :first_name, :last_name, :final_time, :final_time_ms, :stopped_at)'
        )->execute($d);
    }

    public function testSchemaCanBeRunTwice(): void
    {
        $this->pdo->exec(file_get_contents(__DIR__ . '/../schemas.sql'));
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM scores')->fetchColumn());
    }

    public function testValidScoreIsInserted(): void
    {
        $this->insert();
        $row = $this->pdo->query('SELECT * FROM scores')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('Ada', $row['first_name']);
        $this->assertNotEmpty($row['created_at'], 'created_at doit avoir une valeur par défaut');
    }

    /** @dataProvider invalidStages */
    public function testStoppedAtMustBeBetween1And5(int $stage): void
    {
        $this->expectException(PDOException::class);
        $this->insert(['stopped_at' => $stage]);
    }

    public static function invalidStages(): array
    {
        return [[0], [6], [-1]];
    }

    public function testValidStagesAreAccepted(): void
    {
        foreach ([1, 2, 3, 4, 5] as $stage) {
            $this->insert(['stopped_at' => $stage]);
        }
        $this->assertSame(5, (int) $this->pdo->query('SELECT COUNT(*) FROM scores')->fetchColumn());
    }

    public function testPhotoIsRequired(): void
    {
        $this->expectException(PDOException::class);
        $this->pdo->exec(
            "INSERT INTO scores (first_name, last_name, final_time, final_time_ms, stopped_at)
             VALUES ('A', 'B', '00:00:01', 1000, 1)"
        );
    }

    public function testLeaderboardIsSortedLikeGetLeaderboardPhp(): void
    {
        // Même ORDER BY que api/get_leaderboard.php : étape la plus avancée d'abord, puis temps le plus court
        $this->insert(['first_name' => 'LentEtape5',   'stopped_at' => 5, 'final_time_ms' => 90000]);
        $this->insert(['first_name' => 'RapideEtape2', 'stopped_at' => 2, 'final_time_ms' => 10000]);
        $this->insert(['first_name' => 'RapideEtape5', 'stopped_at' => 5, 'final_time_ms' => 30000]);

        $names = $this->pdo->query(
            'SELECT first_name FROM scores ORDER BY stopped_at DESC, final_time_ms ASC'
        )->fetchAll(PDO::FETCH_COLUMN);

        $this->assertSame(['RapideEtape5', 'LentEtape5', 'RapideEtape2'], $names);
    }
}