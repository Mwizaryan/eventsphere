<?php

use PHPUnit\Framework\TestCase;

/**
 * AdminServiceCrudTest
 * 
 * Strict TDD test for Admin-only Edit and Delete capabilities for services.
 */
class AdminServiceCrudTest extends TestCase
{
    protected $pdo;

    protected function setUp(): void
    {
        // 1. Setup SQLite in-memory database
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // 2. Create schema
        $this->pdo->exec("CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT, 
            name TEXT, 
            email TEXT UNIQUE, 
            password_hash TEXT, 
            is_admin INTEGER DEFAULT 0, 
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        $this->pdo->exec("CREATE TABLE services (
            id INTEGER PRIMARY KEY AUTOINCREMENT, 
            category TEXT, 
            title TEXT, 
            description TEXT, 
            price DECIMAL(10, 2), 
            image_url TEXT
        )");

        // 3. Initialize environment
        $_SESSION = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
    }

    protected function tearDown(): void
    {
        $this->pdo = null;
    }

    /**
     * Helper to simulate script execution
     */
    protected function runScript($scriptPath, $postData)
    {
        $_POST = $postData;
        $pdo = $this->pdo; // Make PDO available to the included script

        ob_start();
        try {
            $fullPath = __DIR__ . '/../' . $scriptPath;
            if (file_exists($fullPath)) {
                include $fullPath;
            } else {
                echo json_encode(['success' => false, 'message' => 'Script not found']);
            }
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        $output = ob_get_clean();
        return json_decode($output, true);
    }

    // ─── EDIT SERVICE TESTS ──────────────────────────────────────────

    /**
     * Test Case 1: Edit Success (Admin)
     */
    public function testEditServiceSuccess()
    {
        // Arrange: Logged in as Admin
        $_SESSION['user_id'] = 1;
        $this->pdo->exec("INSERT INTO users (id, name, email, password_hash, is_admin) VALUES (1, 'Admin User', 'admin@example.com', 'hash', 1)");
        
        // Insert a service to edit
        $this->pdo->exec("INSERT INTO services (id, title, category, price) VALUES (50, 'Old Title', 'venue', 100.00)");

        $updateData = [
            'service_id' => 50,
            'title'      => 'New Premium Title',
            'category'   => 'venue',
            'price'      => 250.00,
            'description'=> 'Updated description'
        ];

        // Act
        $response = $this->runScript('backend/edit_service.php', $updateData);

        // Assert
        $this->assertIsArray($response, "Response should be JSON");
        $this->assertTrue($response['success'], "Admin should be able to edit service");

        $stmt = $this->pdo->prepare("SELECT title, price FROM services WHERE id = ?");
        $stmt->execute([50]);
        $service = $stmt->fetch();
        $this->assertEquals('New Premium Title', $service['title']);
        $this->assertEquals(250.00, $service['price']);
    }

    /**
     * Test Case 2: Edit Security (Normal User)
     */
    public function testEditServiceUnauthorized()
    {
        // Arrange: Logged in as Normal User
        $_SESSION['user_id'] = 2;
        $this->pdo->exec("INSERT INTO users (id, name, email, password_hash, is_admin) VALUES (2, 'Normal User', 'user@example.com', 'hash', 0)");
        
        $this->pdo->exec("INSERT INTO services (id, title, category, price) VALUES (50, 'Original Title', 'venue', 100.00)");

        $updateData = [
            'service_id' => 50,
            'title'      => 'Hacker Title',
            'price'      => 0.01
        ];

        // Act
        $response = $this->runScript('backend/edit_service.php', $updateData);

        // Assert
        $this->assertFalse($response['success'], "Normal user should be rejected");
        
        $stmt = $this->pdo->query("SELECT title FROM services WHERE id = 50");
        $this->assertEquals('Original Title', $stmt->fetch()['title'], "Database should remain unchanged");
    }

    // ─── DELETE SERVICE TESTS ────────────────────────────────────────

    /**
     * Test Case 3: Delete Success (Admin)
     */
    public function testDeleteServiceSuccess()
    {
        // Arrange: Admin logged in
        $_SESSION['user_id'] = 1;
        $this->pdo->exec("INSERT INTO users (id, name, email, password_hash, is_admin) VALUES (1, 'Admin', 'admin@ex.com', 'hash', 1)");
        $this->pdo->exec("INSERT INTO services (id, title, category, price) VALUES (99, 'Delete Me', 'catering', 50.00)");

        // Act
        $response = $this->runScript('backend/delete_service.php', ['service_id' => 99]);

        // Assert
        $this->assertTrue($response['success'], "Admin should be able to delete service");
        
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM services WHERE id = 99");
        $this->assertEquals(0, $stmt->fetchColumn(), "Service should be removed from database");
    }

    /**
     * Test Case 4: Delete Security (Normal User)
     */
    public function testDeleteServiceUnauthorized()
    {
        // Arrange: Normal user logged in
        $_SESSION['user_id'] = 2;
        $this->pdo->exec("INSERT INTO users (id, name, email, password_hash, is_admin) VALUES (2, 'User', 'user@ex.com', 'hash', 0)");
        $this->pdo->exec("INSERT INTO services (id, title, category, price) VALUES (99, 'Stay Here', 'catering', 50.00)");

        // Act
        $response = $this->runScript('backend/delete_service.php', ['service_id' => 99]);

        // Assert
        $this->assertFalse($response['success'], "Normal user should not be able to delete");
        
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM services WHERE id = 99");
        $this->assertEquals(1, $stmt->fetchColumn(), "Service should still exist in database");
    }
}
