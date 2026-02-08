let currentStep = 1;
document.getElementById('sendCodeBtn').addEventListener('click', function() {
    const mobile = document.getElementById('mobile').value;
    if (!mobile || mobile.length !== 9) { showError('Please enter a valid 9-digit mobile number'); return; }

    if (!document.getElementById('plan_id').value || !document.getElementById('agent_id').value) {
        showError('Please select a plan and agent'); return;
    }

    const mobileNumber = '+963' + mobile;
    const btnText = this.querySelector('.btn-text');
    const btnLoading = this.querySelector('.btn-loading');
    
    btnText.style.display = 'none';
    btnLoading.style.display = 'flex';
    this.disabled = true;

    const formData = new FormData();
    formData.append('action', 'create_user');
    formData.append('plan_id', document.getElementById('plan_id').value);
    formData.append('agent_id', document.getElementById('agent_id').value);
    formData.append('mobile', mobileNumber);

    fetch('signup.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showStep(2);
                document.getElementById('successUsername').textContent = data.username;
                document.getElementById('successPassword').textContent = data.password;
            } else {
                showError(data.message || 'Failed to create account.');
            }
        })
        .catch(e => showError('Network error: ' + e.message))
        .finally(() => {
            btnText.style.display = 'flex';
            btnLoading.style.display = 'none';
            this.disabled = false;
        });
});

function showStep(step) {
    document.querySelectorAll('.step').forEach(s => s.style.display = 'none');
    document.getElementById('step' + step).style.display = 'block';
    document.getElementById('errorMessage').style.display = 'none';
}
function showError(msg) {
    document.getElementById('errorText').textContent = msg;
    document.getElementById('errorMessage').style.display = 'flex';
}
