<?php
// Set content type to JSON
header("Content-Type: application/json");

// Include the database connection file
include 'db_connection.php';

// Get the action from GET or POST request
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Use a switch statement to handle different API actions
switch ($action) {
    case 'login':
        // Get student ID and password from POST data
        $studentId = $_POST['student_id'] ?? '';
        $password = $_POST['password'] ?? '';

        // Prepare a SQL statement to prevent SQL injection
        $stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ? AND password = ?");
        $stmt->bind_param("ss", $studentId, $password);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $student = $result->fetch_assoc();
            // Check if the student has already voted
            if ($student['voted']) {
                echo json_encode(['success' => false, 'message' => 'You have voted already.']);
            } else {
                echo json_encode(['success' => true, 'student' => $student]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid Credentials. Check the admin/headmaster for clarification or try again.']);
        }
        $stmt->close();
        break;

    case 'getCandidates':
        // Fetch all candidates and return them
        $candidates = [];
        $positions = ['Head Boy', 'Head Girl', 'Sports Captain'];
        foreach ($positions as $position) {
            $stmt = $conn->prepare("SELECT id, name, position, votes, photo_url FROM candidates WHERE position = ?");
            $stmt->bind_param("s", $position);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $candidates[] = $row;
            }
            $stmt->close();
        }
        echo json_encode(['success' => true, 'candidates' => $candidates]);
        break;

    case 'addVote':
        // Get candidate ID and student ID
        $candidateId = $_POST['candidate_id'] ?? 0;
        $studentId = $_POST['student_id'] ?? '';

        // Start a transaction for atomicity
        $conn->begin_transaction();
        try {
            // Check if student has already voted
            $stmt_check = $conn->prepare("SELECT voted FROM students WHERE student_id = ?");
            $stmt_check->bind_param("s", $studentId);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            $student = $result_check->fetch_assoc();
            $stmt_check->close();

            if ($student['voted']) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'You have voted already.']);
                break;
            }

            // Update the candidate's vote count
            $stmt_vote = $conn->prepare("UPDATE candidates SET votes = votes + 1 WHERE id = ?");
            $stmt_vote->bind_param("i", $candidateId);
            $stmt_vote->execute();

            // Mark the student as having voted
            $stmt_voted = $conn->prepare("UPDATE students SET voted = 1 WHERE student_id = ?");
            $stmt_voted->bind_param("s", $studentId);
            $stmt_voted->execute();

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Vote cast successfully.']);

        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Failed to cast vote: ' . $e->getMessage()]);
        }
        break;

    case 'getStats':
        // Get vote counts for all candidates
        $stats = [];
        $stmt = $conn->prepare("SELECT name, votes, position FROM candidates ORDER BY votes DESC");
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $stats[] = $row;
        }
        $stmt->close();
        echo json_encode(['success' => true, 'stats' => $stats]);
        break;

    case 'addCandidate':
        // Get candidate details from POST
        $name = $_POST['name'] ?? '';
        $position = $_POST['position'] ?? '';
        $photo_url = $_POST['photo_url'] ?? '';

        $stmt = $conn->prepare("INSERT INTO candidates (name, position, photo_url) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $position, $photo_url);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Candidate added.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add candidate.']);
        }
        $stmt->close();
        break;

    case 'updateCandidate':
        // Update a candidate's details
        $id = $_POST['id'] ?? 0;
        $name = $_POST['name'] ?? '';
        $position = $_POST['position'] ?? '';
        $photo_url = $_POST['photo_url'] ?? '';

        $stmt = $conn->prepare("UPDATE candidates SET name = ?, position = ?, photo_url = ? WHERE id = ?");
        $stmt->bind_param("sssi", $name, $position, $photo_url, $id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Candidate updated.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update candidate.']);
        }
        $stmt->close();
        break;

    case 'deleteCandidate':
        // Delete a candidate by ID
        $id = $_POST['id'] ?? 0;
        $stmt = $conn->prepare("DELETE FROM candidates WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Candidate deleted.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete candidate.']);
        }
        $stmt->close();
        break;

    case 'wipeResults':
        // Wipe all election results by resetting student and candidate votes
        $conn->begin_transaction();
        try {
            $conn->query("UPDATE students SET voted = 0");
            $conn->query("UPDATE candidates SET votes = 0");
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Election results wiped.']);
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Failed to wipe results: ' . $e->getMessage()]);
        }
        break;

    default:
        // Handle unknown actions
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
        break;
}

// Close the database connection at the end
$conn->close();
?>
