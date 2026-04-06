<!-- Common JavaScript Utilities for Appointment Rendering -->
<script>
/**
 * Shared Age Calculation Logic
 * Ensures consistency across Patient, Doctor, and Admin views
 */
function calculateAgeModal(dateOfBirth) {
    if (!dateOfBirth || dateOfBirth === '' || dateOfBirth === '0000-00-00') return 'N/A';
    const today = new Date();
    const birthDate = new Date(dateOfBirth);
    
    // Safety check for future dates
    if (birthDate > today) return 'N/A';
    
    let age = today.getFullYear() - birthDate.getFullYear();
    const m = today.getMonth() - birthDate.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
        age--;
    }
    return age < 0 ? 'N/A' : age;
}

/**
 * Shared Formatting Utilities
 */
function formatDateModal(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
}

function formatTimeModal(timeString) {
    if (!timeString) return 'N/A';
    const parts = timeString.split(':');
    const hours = parseInt(parts[0], 10);
    const minutes = parts[1];
    const ampm = hours >= 12 ? 'PM' : 'AM';
    const displayHour = hours % 12 || 12;
    return `${displayHour}:${minutes} ${ampm}`;
}

/**
 * Main Rendering Function for Appointment Overview
 * @param {Object} data - The appointment data object
 * @param {String} portalType - 'patient', 'doctor', or 'admin' 
 */
function showAppointmentOverview(data, portalType = 'patient') {
    window.currentAppointmentData = data;
    const modal = document.getElementById('appointmentModal');
    const content = document.getElementById('modalContent');
    const footer = document.getElementById('modalFooter');
    
    if (!modal || !content || !footer) {
        console.error('Appointment modal elements not found in DOM.');
        return;
    }

    // Standardize field names for common usage
    const name = data.name || (data.patient_first_name + ' ' + (data.patient_last_name || ''));
    const id = data.id || data.appointment_id;
    const status = (data.status || 'pending').toLowerCase();
    const initials = name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
    
    // Handle Rescheduling Reason
    let rescheduleInfoHtml = '';
    if (status === 'rescheduled') {
        rescheduleInfoHtml = `
            <div style="grid-column: span 2; background: white; border: 1px solid #eef2f6; border-radius: 20px; padding: 28px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); overflow: hidden;">
                <h3 style="background: linear-gradient(135deg, #f59e0b, #d97706); color: white; margin: -28px -28px 24px -28px; padding: 16px 28px; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-clock-rotate-left" style="color: white; font-size: 0.9rem;"></i> Reason for Rescheduling
                </h3>
                <div style="font-size: 0.95rem; color: #92400e; font-weight: 600; line-height: 1.6; font-style: italic; background: #fffbeb; padding: 18px 22px; border-radius: 12px; border: 1px solid #fef3c7;">
                    "${data.reschedule_reason || 'No specific reason provided.'}"
                </div>
            </div>
        `;
    }

    // Handle clinical records (Findings)
    let clinicalRecordsHtml = '';
    const hasFindings = (status === 'completed' && data.notes);
    
    if (portalType === 'doctor' || portalType === 'admin' || (portalType === 'patient' && status === 'completed')) {
        clinicalRecordsHtml = `
            <div style="grid-column: span 2; background: white; border: 1px solid #eef2f6; border-radius: 20px; padding: 28px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); overflow: hidden;">
                <h3 style="background: #2563eb; color: white; margin: -28px -28px 24px -28px; padding: 16px 28px; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-file-medical-alt" style="color: white; font-size: 0.9rem;"></i> ${status === 'completed' ? 'Clinical Records' : 'Medical Reason'}
                </h3>
                <div style="display: flex; flex-direction: column; gap: 20px;">
                    <div>
                        <label style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 8px;">Reason for Visit</label>
                        <div style="font-size: 0.95rem; color: #1e293b; font-weight: 500; line-height: 1.5;">${data.reason || data.illness || 'N/A'}</div>
                    </div>
                    
                    ${status === 'completed' ? `
                    <div style="background: #eff6ff; border: 1px solid #dbeafe; border-radius: 12px; padding: 18px 20px; position: relative;">
                        ${data.updated_at ? `
                            <div style="font-size: 0.7rem; color: #64748b; font-weight: 600; display: flex; align-items: center; gap: 4px; margin-bottom: 8px;">
                                <i class="fas fa-history" style="font-size: 0.65rem; color: #2563eb;"></i>
                                Findings finalized on ${new Date(data.updated_at).toLocaleString('en-US', { 
                                    month: 'long', 
                                    day: 'numeric', 
                                    year: 'numeric', 
                                    hour: '2-digit', 
                                    minute: '2-digit',
                                    hour12: true 
                                })}
                            </div>
                        ` : ''}
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                            <label style="display: block; font-size: 0.7rem; color: #2563eb; font-weight: 800; text-transform: uppercase;">Doctor's Findings</label>
                        </div>
                        <div style="font-size: 1rem; color: #1e40af; line-height: 1.6; font-weight: 600; font-style: italic; background: rgba(255,255,255,0.4); padding: 12px; border-radius: 8px;">
                            ${data.notes || '"No findings recorded yet."'}
                        </div>
                    </div>
                    ` : ''}
                </div>
            </div>
        `;
    }

    content.innerHTML = `
        <div class="appointment-details-premium" style="background: #fdfdfd; padding: 0; font-family: 'Inter', system-ui, -apple-system, sans-serif;">
            
            <div style="background: white; border-bottom: 1px solid #edf2f7; padding: 32px 40px; display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 24px;">
                    <div style="width: 72px; height: 72px; background: linear-gradient(135deg, #2563eb, #3b82f6); color: white; border-radius: 20px; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.75rem; box-shadow: 0 10px 20px rgba(37, 99, 235, 0.15);">
                        ${initials}
                    </div>
                    <div>
                        <h1 style="color: #0f172a; font-size: 2rem; font-weight: 800; margin: 0; letter-spacing: -0.04em;">${name}</h1>
                        <div style="display: flex; align-items: center; gap: 12px; margin-top: 6px;">
                            <span style="color: #64748b; font-size: 0.95rem; font-weight: 600;">ID: <span style="color: #2563eb; font-weight: 700;">#APT-${id.toString().padStart(5, '0')}</span></span>
                            <span style="width: 4px; height: 4px; background: #cbd5e1; border-radius: 50%;"></span>
                            <span class="status-badge status-${status}" style="font-size: 0.8rem; padding: 4px 12px; border-radius: 6px; font-weight: 800; letter-spacing: 0.05em;">${status.toUpperCase()}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div style="padding: 40px; display: grid; grid-template-columns: repeat(2, 1fr); gap: 32px;">
                
                <div style="background: white; border: 1px solid #eef2f6; border-radius: 20px; padding: 28px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); overflow: hidden;">
                    <h3 style="background: #2563eb; color: white; margin: -28px -28px 24px -28px; padding: 16px 28px; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-calendar-alt" style="color: white; font-size: 0.9rem;"></i> Core Schedule
                    </h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                        <div>
                            <label style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 6px;">Date</label>
                            <div style="font-size: 1rem; font-weight: 600; color: #1e293b;">${data.date}</div>
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 6px;">Time Slot</label>
                            <div style="font-size: 1rem; font-weight: 600; color: #1e293b;">${data.time}</div>
                        </div>
                        <div style="grid-column: span 2;">
                            <label style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 6px;">Service Requested</label>
                            <div style="font-size: 1rem; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-stethoscope" style="color: #cbd5e1; font-size: 0.9rem;"></i>
                                ${data.purpose || 'General Consultation'}
                            </div>
                        </div>
                    </div>
                </div>

                <div style="background: white; border: 1px solid #eef2f6; border-radius: 20px; padding: 28px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); overflow: hidden;">
                    <h3 style="background: #2563eb; color: white; margin: -28px -28px 24px -28px; padding: 16px 28px; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-user-md" style="color: white; font-size: 0.9rem;"></i> Medical Expert
                    </h3>
                    <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 20px;">
                        <div style="width: 52px; height: 52px; background: #f8fafc; border: 1px solid #e2e8f0; color: #2563eb; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-weight: 700;">
                            ${data.doctor_first_name ? data.doctor_first_name[0] : (data.doctor ? data.doctor[4] : 'D')}${data.doctor_last_name ? data.doctor_last_name[0] : ''}
                        </div>
                        <div>
                            <div style="font-size: 1.1rem; font-weight: 700; color: #0f172a;">${data.doctor || ('Dr. ' + data.doctor_first_name + ' ' + data.doctor_last_name)}</div>
                            <div style="font-size: 0.85rem; font-weight: 600; color: #2563eb; text-transform: uppercase; letter-spacing: 0.05em;">${data.specialty || 'Medical Practitioner'}</div>
                        </div>
                    </div>
                </div>

                <div style="grid-column: span 2; background: white; border: 1px solid #eef2f6; border-radius: 20px; padding: 28px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); overflow: hidden;">
                    <h3 style="background: #2563eb; color: white; margin: -28px -28px 24px -28px; padding: 16px 28px; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-info-circle" style="color: white; font-size: 0.9rem;"></i> Information Details
                    </h3>
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 32px;">
                        <div>
                            <label style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 8px;">Birthdate</label>
                            <div style="font-size: 0.95rem; font-weight: 600; color: #1e293b;">${data.dob || 'N/A'}</div>
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 8px;">Gender</label>
                            <div style="font-size: 0.95rem; font-weight: 600; color: #1e293b; text-transform: capitalize;">${data.gender || 'N/A'}</div>
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 8px;">Relationship</label>
                            <div style="font-size: 0.95rem; font-weight: 600; color: #1e293b; text-transform: capitalize;">${data.relationship || 'Self'}</div>
                        </div>
                        <div style="grid-column: span 3; padding-top: 16px; border-top: 1px solid #f1f5f9; display: grid; grid-template-columns: 1fr 1fr 1.5fr; gap: 32px;">
                            <div>
                                <label style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 6px;">Email Address</label>
                                <div style="font-size: 0.9rem; color: #475569;">${data.email || 'N/A'}</div>
                            </div>
                            <div>
                                <label style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 6px;">Phone Contact</label>
                                <div style="font-size: 0.9rem; color: #475569;">${data.phone || 'N/A'}</div>
                            </div>
                            <div>
                                <label style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 6px;">Home Address</label>
                                <div style="font-size: 0.9rem; color: #475569; line-height: 1.5;">${data.address || 'N/A'}</div>
                            </div>
                        </div>
                    </div>
                </div>

                ${data.laboratory_image || data.laboratory_image_path ? `
                <div style="grid-column: span 2; background: white; border: 1px solid #eef2f6; border-radius: 20px; padding: 28px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); overflow: hidden;">
                    <h3 style="background: #2563eb; color: white; margin: -28px -28px 24px -28px; padding: 16px 28px; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em;">
                        <i class="fas fa-flask" style="color: white; margin-right: 10px;"></i> Laboratory Request
                    </h3>
                    <div style="background: #f8fafc; border: 1.5px dashed #cbd5e1; border-radius: 16px; padding: 32px; text-align: center;">
                        <img src="../../${data.laboratory_image || data.laboratory_image_path}" alt="Laboratory Request" style="max-width: 100%; max-height: 500px; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); cursor: pointer;" onclick="window.open('../../${data.laboratory_image || data.laboratory_image_path}', '_blank')">
                    </div>
                </div>
                ` : ''}

                <div style="background: white; border: 1px solid #eef2f6; border-radius: 20px; padding: 28px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); overflow: hidden;">
                    <h3 style="background: #2563eb; color: white; margin: -28px -28px 24px -28px; padding: 16px 28px; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-receipt" style="color: white; font-size: 0.9rem;"></i> Payment Summary
                    </h3>
                    <div style="background: #f8fafc; border-radius: 12px; padding: 20px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                            <span style="font-weight: 700; color: #64748b; font-size: 0.8rem; text-transform: uppercase;">Amount Received</span>
                            <span style="font-size: 1.5rem; font-weight: 900; color: #059669;">₱${parseFloat(data.payment_amount || (data.payment ? data.payment.amount : 0)).toFixed(2)}</span>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1.2fr; gap: 20px; padding-top: 16px; border-top: 1px solid #e2e8f0;">
                            <div>
                                <label style="display: block; font-size: 0.7rem; color: #94a3b8; font-weight: 700; text-transform: uppercase; margin-bottom: 4px;">Status</label>
                                <span class="status-badge status-${(data.payment_status || (data.payment ? data.payment.status : 'pending')).toLowerCase()}" style="font-weight: 800; font-size: 0.75rem;">${(data.payment_status || (data.payment ? data.payment.status : 'pending')).toUpperCase()}</span>
                            </div>
                            <div style="text-align: right;">
                                <label style="display: block; font-size: 0.7rem; color: #94a3b8; font-weight: 700; text-transform: uppercase; margin-bottom: 4px;">GCash Ref</label>
                                <span style="font-size: 0.95rem; font-weight: 700; color: #2563eb; font-family: monospace;">${data.gcash_reference || (data.payment ? data.payment.ref : 'N/A')}</span>
                            </div>
                        </div>
                    </div>
                </div>

                ${(data.receipt_path || (data.payment && data.payment.receipt)) ? `
                <div style="background: white; border: 1px solid #eef2f6; border-radius: 20px; padding: 28px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); overflow: hidden;">
                    <h3 style="background: #2563eb; color: white; margin: -28px -28px 24px -28px; padding: 16px 28px; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em;">
                        <i class="fas fa-search-dollar" style="color: white; margin-right: 10px;"></i> Evidence of Transaction
                    </h3>
                    <div style="background: #f8fafc; border: 1.5px dashed #cbd5e1; border-radius: 16px; padding: 32px; text-align: center;">
                        <img src="../../${data.receipt_path || data.payment.receipt}" alt="Receipt" style="max-width: 100%; max-height: 500px; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); cursor: pointer;" onclick="window.open('../../${data.receipt_path || data.payment.receipt}', '_blank')">
                    </div>
                </div>
                ` : ''}

                ${rescheduleInfoHtml}
                ${clinicalRecordsHtml}

            </div>
        </div>
    `;

    // Shared Footer Logic based on Portal Type
    let footerHtml = `
        <button type="button" class="modal-btn modal-btn-secondary" onclick="closeBaseModal()" style="padding: 12px 24px; border-radius: 10px; font-weight: 600; cursor: pointer; border: 1px solid #e2e8f0; background: white; color: #475569; transition: all 0.2s;">Close</button>
        <button type="button" class="modal-btn modal-btn-secondary" onclick="window.print()" style="padding: 12px 24px; border-radius: 10px; font-weight: 600; cursor: pointer; border: 1px solid #e2e8f0; background: white; color: #475569; transition: all 0.2s;"><i class="fas fa-print"></i> Print Details</button>
    `;
    if (portalType === 'doctor') {
        if (data.can_complete) {
            footerHtml = `
                <div style="flex: 1; display: flex; gap: 12px; justify-content: flex-start;">
                    <button type="button" class="modal-btn modal-btn-primary" 
                            onclick="openFindingsModal(window.currentAppointmentData.id, window.currentAppointmentData.notes || '', 'complete')" 
                            style="padding: 12px 28px; border-radius: 12px; font-weight: 700; cursor: pointer; border: none; background: linear-gradient(135deg, #10b981, #059669); color: white; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2); transition: all 0.2s;">
                        <i class="fas fa-check-circle"></i> Complete Appointment
                    </button>
                </div>
                ${footerHtml}
            `;
        } else if (data.can_add_findings) {
            footerHtml = `
                <div style="flex: 1;"></div>
                <button type="button" class="modal-btn modal-btn-primary" 
                        onclick="openFindingsModal(window.currentAppointmentData.id, window.currentAppointmentData.notes || '', 'update_findings')" 
                        style="padding: 12px 24px; border-radius: 10px; font-weight: 600; cursor: pointer; border: none; background: linear-gradient(135deg, #2563eb, #1e3a8a); color: white; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2); transition: all 0.2s;">
                    <i class="fas fa-pen"></i> Update Findings
                </button>
                ${footerHtml}
            `;
        }
    } else if (portalType === 'admin') {
        const pStatus = (data.payment_status || (data.payment ? data.payment.status : '')).toLowerCase();
        
        let primaryActionHtml = '';
        
        // Scenario 1: Both Payment and Appointment are Pending
        if (pStatus === 'pending_verification' && status === 'pending') {
            primaryActionHtml = `
                <form method="POST" style="display: inline-block;">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="status" value="scheduled">
                    <input type="hidden" name="verify_payment" value="1">
                    <input type="hidden" name="appointment_id" value="${data.id}">
                    <button type="submit" class="modal-btn modal-btn-primary" style="padding: 12px 28px; border-radius: 12px; font-weight: 700; cursor: pointer; border: none; background: linear-gradient(135deg, #10b981, #059669); color: white; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);" onclick="return confirm('Full Processing: Do you want to verify the payment and secure this clinical slot in one operation?')">
                        <i class="fas fa-check-double"></i> Verify & Schedule
                    </button>
                </form>
            `;
        } 
        // Scenario 2: Only Payment needs verification (for active/pending appointments only)
        else if (pStatus === 'pending_verification' && status !== 'completed' && status !== 'cancelled' && status !== 'no_show') {
            primaryActionHtml = `
                <form method="POST" style="display: inline-block;">
                    <input type="hidden" name="action" value="confirm_payment">
                    <input type="hidden" name="appointment_id" value="${data.id}">
                    <button type="submit" class="modal-btn" style="padding: 12px 28px; border-radius: 12px; font-weight: 700; cursor: pointer; border: none; background: linear-gradient(135deg, #fbbf24, #d97706); color: white;" onclick="return confirm('Verify clinical payment record only?')">
                        <i class="fas fa-money-check-alt"></i> Verify Payment
                    </button>
                </form>
            `;
        }
        // Scenario 3: Only Appointment needs scheduling
        else if (status === 'pending') {
            primaryActionHtml = `
                <form method="POST" style="display: inline-block;">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="status" value="scheduled">
                    <input type="hidden" name="appointment_id" value="${data.id}">
                    <button type="submit" class="modal-btn modal-btn-primary" style="padding: 12px 28px; border-radius: 12px; font-weight: 700; cursor: pointer; border: none; background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: white;" onclick="return confirm('Secure this appointment slot in the calendar?')">
                        <i class="fas fa-calendar-check"></i> Schedule Appointment
                    </button>
                </form>
            `;
        }

        if (primaryActionHtml) {
            footerHtml = `
                <div style="flex: 1; display: flex; gap: 8px; justify-content: flex-start;">
                    ${primaryActionHtml}
                </div>
                ${footerHtml}
            `;
        }
    }

    footer.innerHTML = footerHtml;
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeBaseModal() {
    const modal = document.getElementById('appointmentModal');
    if (modal) modal.style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Window click listener for the base modal
window.addEventListener('click', function(event) {
    const modal = document.getElementById('appointmentModal');
    if (event.target === modal) {
        closeBaseModal();
    }
});
</script>

<!-- Shared Modal HTML Structure -->
<div id="appointmentModal" class="modal" style="z-index: 10000; display: none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); align-items: center; justify-content: center;">
    <div class="modal-content" style="max-width: 1000px; width: 95%; max-height: 85vh; overflow-y: auto; border-radius: 20px; border: none; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); position: relative; margin: 0 auto; background: white; padding: 0;">
        <div class="modal-header" style="background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); color: white; padding: 20px 40px; border-radius: 20px 20px 0 0; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100;">
            <h3 style="margin: 0; font-size: 1.4rem; font-weight: 700; display: flex; align-items: center; gap: 12px;">
                <i class="fas fa-file-medical"></i> Appointment Overview
            </h3>
            <span class="close-modal" onclick="closeBaseModal()" style="color: rgba(255, 255, 255, 0.8); font-size: 1.5rem; cursor: pointer; transition: all 0.2s;"><i class="fas fa-times"></i></span>
        </div>
        <div class="modal-body" id="modalContent" style="padding: 0; background: #fdfdfd;">
            <!-- Rendered Content Injected here -->
        </div>
        <div class="modal-footer" id="modalFooter" style="background: #f8fafc; border-top: 1px solid #edf2f7; padding: 24px 40px; border-radius: 0 0 20px 20px; display: flex; gap: 12px; align-items: center; justify-content: flex-end;">
            <!-- Buttons Injected here -->
        </div>
    </div>
</div>
