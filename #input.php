<?php
// Database connection parameters
$host = 'localhost'; // or your database host
$db = 'graduation';
$user = 'root';
$pass = '*******';

// Create connection
$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Path to your CSV file
$csvFile = 'qr.csv';

// Open the file
if (($handle = fopen($csvFile, 'r')) !== FALSE) {
    // Skip the header row
    fgetcsv($handle);

    // Prepare an SQL statement
    $stmt = $conn->prepare("INSERT INTO tickets (class, class_number, engname, name, ticket_code) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $class, $class_number, $engname, $name, $ticket_code);

    // Read each row of the CSV
    while (($data = fgetcsv($handle)) !== FALSE) {
        // Assign data to variables
        $class = $data[0];
        $class_number = $data[1];
        $engname = $data[2];
        $name = $data[3];
        $ticket_code = $data[4];

        // Execute the prepared statement
        $stmt->execute();
    }

    // Close the file and statement
    fclose($handle);
    $stmt->close();
}

// Close the database connection
$conn->close();

echo "Data imported successfully!";
?>