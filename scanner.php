<!DOCTYPE html>
<html>
<head>
    <title>QR Attendance Scanner</title>
    <script src="https://rawgit.com/schmich/instascan-builds/master/instascan.min.js"></script>
</head>
<body>
    <video id="preview" style="width: 100%; height: auto;"></video>
    <script>
        let scanner = new Instascan.Scanner({
            video: document.getElementById('preview')
        });

        scanner.addListener('scan', function (content) {
            // Send scan result to server
            fetch('process_attendance.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ qr_data: content })
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    alert(data.message);
                    window.close(); // Close scanner after successful scan
                } else {
                    alert('Error: ' + data.message);
                }
            });
        });

        Instascan.Camera.getCameras().then(function (cameras) {
            if (cameras.length > 0) {
                scanner.start(cameras[0]);
            } else {
                console.error('No cameras found');
            }
        });
    </script>
</body>
</html>