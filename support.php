<?php
require_once __DIR__ . '/includes/core/bootstrap.php';

// Get company info from subdomain or default
$companyId = null;
$companyName = 'Support';

if (isset($_SERVER['HTTP_HOST'])) {
    $host = $_SERVER['HTTP_HOST'];
    $parts = explode('.', $host);

    if (count($parts) > 2) {
        $subdomain = $parts[0];
        $stmt = $pdo->prepare("SELECT id, name FROM companies WHERE subdomain = ? AND status = 'active'");
        $stmt->execute([$subdomain]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($company) {
            $companyId = $company['id'];
            $companyName = $company['name'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Support Ticket - <?= htmlspecialchars($companyName) ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 0;
        }

        .support-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .support-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .support-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }

        .support-header h1 {
            margin: 0;
            font-size: 2rem;
            font-weight: 600;
        }

        .support-header p {
            margin: 10px 0 0;
            opacity: 0.9;
        }

        .support-body {
            padding: 40px;
        }

        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }

        .form-control, .form-select {
            border-radius: 8px;
            border: 2px solid #e0e0e0;
            padding: 12px 15px;
            transition: all 0.3s;
        }

        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 15px 40px;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 8px;
            transition: transform 0.2s;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        .success-message {
            display: none;
            text-align: center;
            padding: 40px;
        }

        .success-message i {
            font-size: 4rem;
            color: #28a745;
            margin-bottom: 20px;
        }

        .queue-option {
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .queue-option:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }

        .queue-option.selected {
            border-color: #667eea;
            background: #f8f9ff;
        }
    </style>
</head>
<body>
    <div class="support-container">
        <div class="support-card">
            <div class="support-header">
                <i class="bi bi-headset" style="font-size: 3rem;"></i>
                <h1>Submit a Support Ticket</h1>
                <p>We're here to help. Please provide details about your issue.</p>
            </div>

            <div class="support-body" id="ticketForm">
                <form id="submitForm">
                    <?php if ($companyId): ?>
                        <input type="hidden" name="company_id" value="<?= $companyId ?>">
                    <?php endif; ?>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Your Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="contact_name" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="contact_email" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Phone Number (Optional)</label>
                        <input type="tel" class="form-control" name="contact_phone">
                    </div>

                    <div class="mb-3" id="queueSelection" style="display: none;">
                        <label class="form-label">Department</label>
                        <div id="queueOptions"></div>
                        <input type="hidden" name="queue_id" id="selectedQueueId">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Subject <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="subject" required placeholder="Brief description of your issue">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="description" rows="6" required placeholder="Please provide as much detail as possible..."></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Attachments (Optional)</label>
                        <input type="file" class="form-control" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.txt">
                        <small class="text-muted">You can attach images, PDFs, or documents</small>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-submit">
                            <i class="bi bi-send"></i> Submit Ticket
                        </button>
                    </div>
                </form>
            </div>

            <div class="success-message" id="successMessage">
                <i class="bi bi-check-circle-fill"></i>
                <h2>Ticket Submitted Successfully!</h2>
                <p class="lead">Your ticket number is: <strong id="ticketNumber"></strong></p>
                <p>We've received your support request and will respond as soon as possible.</p>
                <p class="text-muted">You should receive a confirmation email at the address you provided.</p>
                <button class="btn btn-primary mt-3" onclick="resetForm()">Submit Another Ticket</button>
            </div>
        </div>

        <!-- Existing Customer Login -->
        <div class="text-center mt-4">
            <a href="/portal.php" class="text-white text-decoration-none">
                <i class="bi bi-box-arrow-in-right"></i>
                Already have a ticket? Check your ticket status
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            loadQueues();

            document.getElementById('submitForm').addEventListener('submit', handleSubmit);
        });

        async function loadQueues() {
            <?php if ($companyId): ?>
            try {
                const formData = new FormData();
                formData.append('action', 'queues');
                formData.append('company_id', '<?= $companyId ?>');

                const response = await fetch('/api/submit-ticket.php?action=queues', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success && result.data.length > 0) {
                    document.getElementById('queueSelection').style.display = 'block';
                    renderQueues(result.data);
                }
            } catch (error) {
                console.error('Error loading queues:', error);
            }
            <?php endif; ?>
        }

        function renderQueues(queues) {
            const container = document.getElementById('queueOptions');

            container.innerHTML = queues.map(queue => `
                <div class="queue-option" onclick="selectQueue(${queue.id})">
                    <i class="bi bi-${queue.icon}" style="color: ${queue.color}; font-size: 1.5rem;"></i>
                    <strong class="d-block">${escapeHtml(queue.name)}</strong>
                    ${queue.description ? `<small class="text-muted">${escapeHtml(queue.description)}</small>` : ''}
                </div>
            `).join('');
        }

        function selectQueue(queueId) {
            // Remove previous selection
            document.querySelectorAll('.queue-option').forEach(opt => {
                opt.classList.remove('selected');
            });

            // Add selection to clicked option
            event.currentTarget.classList.add('selected');

            // Set hidden input
            document.getElementById('selectedQueueId').value = queueId;
        }

        async function handleSubmit(e) {
            e.preventDefault();

            const form = e.target;
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Submitting...';

            try {
                const formData = new FormData(form);

                const response = await fetch('/api/submit-ticket.php?action=submit', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    document.getElementById('ticketNumber').textContent = result.ticket_number;
                    document.getElementById('ticketForm').style.display = 'none';
                    document.getElementById('successMessage').style.display = 'block';
                } else {
                    alert('Error: ' + result.message);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            } catch (error) {
                alert('Error submitting ticket: ' + error.message);
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        }

        function resetForm() {
            document.getElementById('submitForm').reset();
            document.getElementById('ticketForm').style.display = 'block';
            document.getElementById('successMessage').style.display = 'none';

            // Clear queue selection
            document.querySelectorAll('.queue-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            document.getElementById('selectedQueueId').value = '';
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
