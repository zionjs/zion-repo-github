<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GitHub ZIP Uploader - PHP Version</title>
    <style>
        /* Same CSS as HTML/JS version */
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; background-color: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #333; }
        input, select, button { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 16px; }
        button { background-color: #2ea44f; color: white; border: none; cursor: pointer; margin-top: 10px; }
        button:hover { background-color: #2c974b; }
        button:disabled { background-color: #ccc; cursor: not-allowed; }
        .status { margin-top: 20px; padding: 10px; border-radius: 5px; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <div class="container">
        <h1>GitHub ZIP Uploader (PHP Version)</h1>
        
        <form id="uploadForm" enctype="multipart/form-data">
            <div class="form-group">
                <label for="token">GitHub Token:</label>
                <input type="password" id="token" name="token" required>
            </div>

            <div class="form-group">
                <label for="user">GitHub Username:</label>
                <input type="text" id="user" name="user" required>
            </div>

            <div class="form-group">
                <label for="repo">Repository Name:</label>
                <input type="text" id="repo" name="repo" required>
            </div>

            <div class="form-group">
                <label for="branch">Branch:</label>
                <input type="text" id="branch" name="branch" value="main" required>
            </div>

            <div class="form-group">
                <label for="zipFile">Pilih File ZIP:</label>
                <input type="file" id="zipFile" name="zipFile" accept=".zip" required>
            </div>

            <div class="form-group">
                <label for="uploadType">Tipe Upload:</label>
                <select id="uploadType" name="uploadType">
                    <option value="zip-only">Upload ZIP Saja</option>
                    <option value="extract">Upload dan Ekstrak</option>
                </select>
            </div>

            <div class="form-group" id="targetPathGroup" style="display: none;">
                <label for="targetPath">Folder Tujuan:</label>
                <input type="text" id="targetPath" name="targetPath">
            </div>

            <button type="submit" id="uploadBtn">Upload ke GitHub</button>
        </form>

        <div id="status"></div>
    </div>

    <script>
        document.getElementById('uploadType').addEventListener('change', function() {
            document.getElementById('targetPathGroup').style.display = 
                this.value === 'extract' ? 'block' : 'none';
        });

        document.getElementById('uploadForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const uploadBtn = document.getElementById('uploadBtn');
            const statusDiv = document.getElementById('status');

            uploadBtn.disabled = true;
            statusDiv.innerHTML = '<div class="success">Memproses upload...</div>';

            try {
                const response = await fetch('upload.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    let message = 'Upload berhasil!';
                    if (result.files) {
                        message += ` Berhasil mengupload ${result.success} dari ${result.total} file.`;
                    }
                    statusDiv.innerHTML = `<div class="success">${message}</div>`;
                } else {
                    statusDiv.innerHTML = `<div class="error">Error: ${result.error}</div>`;
                }

            } catch (error) {
                statusDiv.innerHTML = `<div class="error">Network error: ${error.message}</div>`;
            } finally {
                uploadBtn.disabled = false;
            }
        });
    </script>
</body>
</html>