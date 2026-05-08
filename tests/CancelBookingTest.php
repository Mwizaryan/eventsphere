<?php

use PHPUnit\Framework\TestCase;

/**
 * CancelBookingTest
 * 
 * Strict TDD test for the "User Cancellation" feature.
 * This test simulates backend logic for a user cancelling their own booking.
 */
class CancelBookingTest extends TestCase
{
    protected $pdo;

    protected function setUp(): void
    {
        // 1. Setup SQLite in-memory database for testing
        // This ensures we don't touch the real MySQL database.
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // 2. Create schema (adapted from schema.sql for SQLite)
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
        
        $this->pdo->exec("CREATE TABLE bookings (
            id INTEGER PRIMARY KEY AUTOINCREMENT, 
            user_id INTEGER, 
            service_id INTEGER, 
            event_date TEXT, 
            status TEXT DEFAULT 'pending',
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (service_id) REFERENCES services(id)
        )");

        // 3. Initialize superglobals for testing environment
        $_SESSION = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
    }

    protected function tearDown(): void
    {
        $this->pdo = null;
    }

    /**
     * Helper to simulate the execution of the backend/cancel_booking.php script.
     * We capture the JSON output to assert the response.
     */
    protected function runCancelBookingScript($bookingId)
    {
        $_POST['booking_id'] = $bookingId;
        
        // We make the test PDO instance available globally so the script can use it
        // instead of connecting to the real database.
        $pdo = $this->pdo; 

        ob_start();
        try {
            // The file does not exist yet. This will fail, which is correct for TDD Step 2.
            $scriptPath = __DIR__ . '/../backend/cancel_booking.php';
            if (file_exists($scriptPath)) {
                include $scriptPath;
            } else {
                echo json_encode(['success' => false, 'message' => 'Script not found']);
            }
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        $output = ob_get_clean();
        
        return json_decode($output, true);
    }

    /**
     * Test Case 1: Success
     * A user should be able to cancel their own 'pending' booking.
     */
    public function testCancelBookingSuccess()
    {
        // Arrange
        $_SESSION['user_id'] = 1;
        $this->pdo->exec("INSERT INTO users (id, name, email, password_hash) VALUES (1, 'Alice', 'alice@example.com', 'hash')");
        $this->pdo->exec("INSERT INTO services (id, title, category, price) VALUES (1, 'Summer Resort', 'venue', 500)");
        $this->pdo->exec("INSERT INTO bookings (id, user_id, service_id, event_date, status) VALUES (101, 1, 1, '2026-06-01', 'pending')");

        // Act
        $response = $this->runCancelBookingScript(101);

        // Assert
        $this->assertIsArray($response, "Output should be a valid JSON array");
        $this->assertTrue($response['success'], "Response success should be true");

        $stmt = $this->pdo->prepare("SELECT status FROM bookings WHERE id = ?");
        $stmt->execute([101]);
        $booking = $stmt->fetch();
        $this->assertEquals('cancelled', $booking['status'], "Database status should be 'cancelled'");
    }

    /**
     * Test Case 2: Security (Wrong User)
     * A user should NOT be able to cancel someone else's booking.
     */
    public function testCancelBookingWrongUser()
    {
        // Arrange: Logged in as User 2
        $_SESSION['user_id'] = 2;
        $this->pdo->exec("INSERT INTO users (id, name, email, password_hash) VALUES (1, 'Alice', 'alice@example.com', 'hash')");
        $this->pdo->exec("INSERT INTO users (id, name, email, password_hash) VALUES (2, 'Bob', 'bob@example.com', 'hash')");
        $this->pdo->exec("INSERT INTO services (id, title, category, price) VALUES (1, 'Summer Resort', 'venue', 500)");
        
        // Booking belongs to User 1
        $this->pdo->exec("INSERT INTO bookings (id, user_id, service_id, event_date, status) VALUES (101, 1, 1, '2026-06-01', 'pending')");

        // Act
        $response = $this->runCancelBookingScript(101);

        // Assert
        $this->assertFalse($response['success'], "Should fail when trying to cancel another user's booking");
        
        $stmt = $this->pdo->prepare("SELECT status FROM bookings WHERE id = ?");
        $stmt->execute([101]);
        $booking = $stmt->fetch();
        $this->assertEquals('pending', $booking['status'], "Booking status should remain 'pending'");
    }

    /**
     * Test Case 3: Business Logic (Already Confirmed)
     * A user should NOT be able to cancel a booking that has already been confirmed by admin.
     */
    public function testCancelAlreadyConfirmed()
    {
        // Arrange
        $_SESSION['user_id'] = 1;
        $this->pdo->exec("INSERT INTO users (id, name, email, password_hash) VALUES (1, 'Alice', 'alice@example.com', 'hash')");
        $this->pdo->exec("INSERT INTO services (id, title, category, price) VALUES (1, 'Summer Resort', 'venue', 500)");
        
        // Booking is already confirmed
        $this->pdo->exec("INSERT INTO bookings (id, user_id, service_id, event_date, status) VALUES (101, 1, 1, '2026-06-01', 'confirmed')");

        // Act
        $response = $this->runCancelBookingScript(101);

        // Assert
        $this->assertFalse($response['success'], "Should fail when booking is already confirmed");
        
        $stmt = $this->pdo->prepare("SELECT status FROM bookings WHERE id = ?");
        $stmt->execute([101]);
        $booking = $stmt->fetch();
        $this->assertEquals('confirmed', $booking['status'], "Booking status should remain 'confirmed'");
    }
}
