
function validation() {

    // Get Values
    let hname = document.getElementById("hname").value.trim();
    let lino = document.getElementById("lino").value.trim();
    let email = document.getElementById("email").value.trim();
    let phone = document.getElementById("phone").value.trim();
    let eno = document.getElementById("eno").value.trim();
    let website = document.getElementById("website").value.trim();
    let address1 = document.getElementById("address1").value.trim();
    let address2 = document.getElementById("address2").value.trim();
    let city = document.getElementById("city").value.trim();
    let state = document.getElementById("state").value.trim();
    let zip = document.getElementById("zip").value.trim();

    let adminname = document.getElementById("adminname").value.trim();
    let adminusername = document.getElementById("adminusername").value.trim();
    let adminemail = document.getElementById("adminemail").value.trim();
    let adminmobile = document.getElementById("adminmobile").value.trim();

    let password = document.getElementById("adminpassword").value;
    let confirm = document.getElementById("adminconfirmpassword").value;

    let file = document.getElementById("license_doc").files[0];

    // Regular Expressions
    let hospitalPattern = /^[A-Za-z0-9 .,'&()-]{3,50}$/;

    let licencePattern = /^[A-Za-z0-9\/-]{5,30}$/;

    let emailPattern =
    /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[A-Za-z]{2,}$/;

    let mobilePattern = /^[6-9][0-9]{9}$/;

    

    let zipPattern = /^[0-9]{6}$/;

    let usernamePattern = /^[A-Za-z0-9_]{5,20}$/;

    let passwordPattern =
    /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&]).{8,20}$/;

    // Hospital Name
    if(!hospitalPattern.test(hname)){
        alert("Enter valid Hospital Name.");
        return false;
    }

    // Licence
    if(!licencePattern.test(lino)){
        alert("Enter valid Registration/Licence Number.");
        return false;
    }

    // Email
    if(!emailPattern.test(email)){
        alert("Enter valid Hospital Email.");
        return false;
    }

    // Phone
    if(!mobilePattern.test(phone)){
        alert("Enter valid Hospital Phone Number.");
        return false;
    }

    // Emergency Number
    if(!mobilePattern.test(eno)){
        alert("Enter valid Emergency Number.");
        return false;
    }

   
  

    
    

    // Zip
    if(!zipPattern.test(zip)){
        alert("Enter valid 6-digit Zip Code.");
        return false;
    }

    // Admin Name
    if(!hospitalPattern.test(adminname)){
        alert("Enter valid Administrator Name.");
        return false;
    }

    // Username
    if(!usernamePattern.test(adminusername)){
        alert("Username should be 5-20 characters.");
        return false;
    }

    // Admin Email
    if(!emailPattern.test(adminemail)){
        alert("Enter valid Administrator Email.");
        return false;
    }

    // Admin Mobile
    if(!mobilePattern.test(adminmobile)){
        alert("Enter valid Administrator Mobile.");
        return false;
    }

    // Password
    if(!passwordPattern.test(password)){
        alert("Password must contain Uppercase, Lowercase, Number and Special Character.");
        return false;
    }

    // Confirm Password
    if(password !== confirm){
        alert("Passwords do not match.");
        return false;
    }

    //  License Document
    if(file){

        let allowed = ["application/pdf","image/jpeg","image/png"];

        if(!allowed.includes(file.type)){
            alert("Only PDF, JPG and PNG files are allowed.");
            return false;
        }

        if(file.size > 200 * 1024){
            alert("License Document must be less than 200 KB.");
            return false;
        }
    }

    return true;
}

