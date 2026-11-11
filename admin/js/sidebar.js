document.addEventListener('DOMContentLoaded',function(){
 const sidebar=document.getElementById('sidebar');
 const toggle=document.getElementById('sidebarToggle');
 if(!sidebar||!toggle)return;
 const saved=localStorage.getItem('hmsSidebarOpen');
 if(saved==='1') sidebar.classList.remove('closed');
 if(saved==='0') sidebar.classList.add('closed');
 toggle.addEventListener('click',function(){
   sidebar.classList.toggle('closed');
   localStorage.setItem('hmsSidebarOpen',sidebar.classList.contains('closed')?'0':'1');
 });
});
