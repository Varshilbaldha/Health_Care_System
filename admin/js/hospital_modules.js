document.addEventListener('DOMContentLoaded', () => {
  const qs = (s, root=document) => root.querySelector(s);
  const qsa = (s, root=document) => [...root.querySelectorAll(s)];
  const open = id => { const el = document.getElementById(id); if (el) el.classList.add('show'); };
  const close = el => { if (el) el.classList.remove('show'); };

  qsa('[data-open]').forEach(btn => btn.addEventListener('click', () => {
    const id = btn.dataset.open;
    const data = btn.dataset.edit ? JSON.parse(btn.dataset.edit) : null;
    if (data) fillEdit(id, data);
    else if (id === 'availabilityModal') resetAvailability();
    open(id);
  }));
  qsa('[data-close]').forEach(btn => btn.addEventListener('click', () => close(btn.closest('.module-modal'))));
  qsa('.module-modal').forEach(modal => modal.addEventListener('click', e => { if (e.target === modal) close(modal); }));

  function set(id, value) { const el=document.getElementById(id); if(el) el.value=value ?? ''; }
  function resetAvailability(){ set('availability_id','');set('availability_doctor','');set('availability_day','Monday');set('availability_start','09:00');set('availability_end','13:00');set('availability_slot','15');set('availability_max','20');set('availability_mode','In-Person');set('availability_status','Active'); const t=qs('#availabilityModalTitle');if(t)t.textContent='Add Availability'; }
  function fillEdit(modalId, d){
    if(modalId==='availabilityModal'){set('availability_id',d.availability_id);set('availability_doctor',d.doctor_id);set('availability_day',d.day_of_week);set('availability_start',(d.start_time||'').slice(0,5));set('availability_end',(d.end_time||'').slice(0,5));set('availability_slot',d.slot_duration_minutes);set('availability_max',d.max_patients);set('availability_mode',d.consultation_mode);set('availability_status',d.status);const t=qs('#availabilityModalTitle');if(t)t.textContent='Edit Availability';}
    if(modalId==='recordModal'){set('medical_record_id',d.medical_record_id);set('record_appointment',d.appointment_id);set('record_status',d.record_status);set('record_complaint',d.chief_complaint);set('record_illness',d.present_illness);set('record_history',d.past_medical_history);set('record_family',d.family_history);set('record_allergies',d.allergies);set('record_notes',d.clinical_notes);set('record_diagnosis',d.diagnosis_summary);set('record_follow',d.follow_up_date);set('record_follow_notes',d.follow_up_notes);}
    if(modalId==='prescriptionModal'){set('prescription_id',d.prescription_id);set('prescription_record',d.medical_record_id);set('prescription_no',d.prescription_no);set('prescription_advice',d.advice);set('prescription_follow',d.follow_up_date);set('prescription_status',d.status);}
    if(modalId==='labModal'){set('lab_test_id',d.lab_test_id);set('lab_record',d.medical_record_id);set('lab_doctor',d.ordered_by_doctor_id);set('lab_name',d.test_name);set('lab_category',d.test_category);set('lab_sample',d.sample_type);set('lab_status',d.test_status);set('lab_date',d.test_date);set('lab_normal',d.normal_range);set('lab_result',d.test_result);set('lab_remarks',d.remarks);}
  }

  qsa('[data-view]').forEach(btn => btn.addEventListener('click', () => {
    const d=JSON.parse(btn.dataset.view); const box=qs('#viewContent'); if(!box)return;
    const omit=['doctor_id','mapping_id','account_id','appointment_id','medical_record_id','availability_id','prescription_id','lab_test_id','admission_id','bed_id','room_id','bill_id','payment_id'];
    box.innerHTML=Object.entries(d).filter(([k])=>!omit.includes(k)).map(([k,v])=>`<div class="view-item"><b>${label(k)}</b><span>${escapeHtml(v===null||v===''?'—':String(v))}</span></div>`).join(''); open('viewModal');
  }));
  qsa('[data-discharge]').forEach(btn => btn.addEventListener('click',()=>{set('discharge_id',btn.dataset.discharge);open('dischargeModal');}));
  function label(k){return k.replace(/_/g,' ').replace(/\b\w/g,m=>m.toUpperCase());}
  function escapeHtml(s){return s.replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));}
});
