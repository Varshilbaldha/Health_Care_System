// ==========================
// Password Show / Hide
// ==========================

const passwordToggles =
    document.querySelectorAll(".password-toggle");


passwordToggles.forEach(function(button){

    button.addEventListener("click", function(){

        const targetId = button.getAttribute("data-target");

        const passwordInput =
            document.getElementById(targetId);

        const icon =
            button.querySelector("i");


        if(passwordInput.type === "password"){

            passwordInput.type = "text";

            icon.classList.remove("fa-eye");

            icon.classList.add("fa-eye-slash");

        }
        else{

            passwordInput.type = "password";

            icon.classList.remove("fa-eye-slash");

            icon.classList.add("fa-eye");

        }

    });

});



// ==========================
// Hospital Form
// ==========================

const hospitalForm =
    document.getElementById("hospitalForm");


hospitalForm.addEventListener("submit", function(event){

    event.preventDefault();

    alert("Hospital information updated successfully!");

});



// ==========================
// Admin Profile Form
// ==========================

const adminProfileForm =
    document.getElementById("adminProfileForm");


adminProfileForm.addEventListener("submit", function(event){

    event.preventDefault();

    alert("Admin profile updated successfully!");

});



// ==========================
// Password Form
// ==========================

const passwordForm =
    document.getElementById("passwordForm");


passwordForm.addEventListener("submit", function(event){

    event.preventDefault();


    const currentPassword =
        document.getElementById("currentPassword").value;

    const newPassword =
        document.getElementById("newPassword").value;

    const confirmPassword =
        document.getElementById("confirmPassword").value;


    // Empty Check

    if(
        currentPassword === "" ||
        newPassword === "" ||
        confirmPassword === ""
    ){

        alert("Please fill all password fields.");

        return;

    }


    // Password Match Check

    if(newPassword !== confirmPassword){

        alert("New password and confirm password do not match.");

        return;

    }


    // Success

    alert("Password updated successfully!");

    passwordForm.reset();

});



// ==========================
// System Settings
// ==========================

const saveSystemSettings =
    document.getElementById("saveSystemSettings");


saveSystemSettings.addEventListener("click", function(){

    alert("System settings saved successfully!");

});