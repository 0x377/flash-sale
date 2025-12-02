import { useState, useEffect } from "react";
import { motion } from "framer-motion";
import { useDispatch, useSelector } from "react-redux";
import { toast } from "react-toastify";
import { signup } from "../store/authSlice";
import "./authStyle.css"

export default function Signup() {
  const [formData, setFormData] = useState({
    fullname: "",
    username: "",
    email: "",
    password: "",
    confirmPassword: ""
  });
  const [errors, setErrors] = useState({});
  const [touched, setTouched] = useState({});

  const dispatch = useDispatch();
  const { user, auth, resError, loading } = useSelector((state) => state.auth);

  // ─────────────────────────────────────────────
  // VALIDATION RULES
  // ─────────────────────────────────────────────

  const validationRules = {
    fullname: (value) => {
      if (!value) return "Full name is required";
      if (value.length < 2) return "Full name must be at least 2 characters";
      if (value.length > 50) return "Full name must be less than 50 characters";
      if (!/^[a-zA-Z\s]+$/.test(value)) return "Full name can only contain letters and spaces";
      return null;
    },
    username: (value) => {
      if (!value) return "Username is required";
      if (value.length < 3) return "Username must be at least 3 characters";
      if (value.length > 20) return "Username must be less than 20 characters";
      if (!/^[a-zA-Z0-9_]+$/.test(value)) return "Username can only contain letters, numbers, and underscores";
      if (!/^[a-zA-Z]/.test(value)) return "Username must start with a letter";
      return null;
    },
    email: (value) => {
      if (!value) return "Email is required";
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(value)) return "Please enter a valid email address";
      return null;
    },
    password: (value) => {
      if (!value) return "Password is required";
      if (value.length < 6) return "Password must be at least 6 characters";
      if (!/(?=.*[a-z])/.test(value)) 
        return "Password must contain at least one lowercase letter";
      if (!/(?=.*[A-Z])/.test(value)) 
        return "Password must contain at least one uppercase letter";
      if (!/(?=.*\d)/.test(value)) 
        return "Password must contain at least one number";
      if (!/(?=.*[!@#$%^&*()_+\-=[\]{};':"\\|,.<>/?])/.test(value))
        return "Password must contain at least one special character";
      return null;
    },
    confirmPassword: (value) => {
      if (!value) return "Please confirm your password";
      if (value !== formData.password) return "Passwords do not match";
      return null;
    }
  };

  // ─────────────────────────────────────────────
  // VALIDATION FUNCTIONS
  // ─────────────────────────────────────────────

  const validateField = (name, value) => {
    const validator = validationRules[name];
    return validator ? validator(value) : null;
  };

  const validateForm = () => {
    const newErrors = {};
    Object.keys(formData).forEach(key => {
      const error = validateField(key, formData[key]);
      if (error) newErrors[key] = error;
    });
    return newErrors;
  };

  // ─────────────────────────────────────────────
  // EFFECTS
  // ─────────────────────────────────────────────

  useEffect(() => {
    if (resError) {
      toast.error(resError, {
        position: "top-right",
        autoClose: 5000,
        hideProgressBar: false,
        closeOnClick: true,
        pauseOnHover: true,
        draggable: true,
      });
    }
  }, [resError]);

  useEffect(() => {
    if (auth && user) {
      toast.success(`Welcome ${user.fullname}! Account created successfully.`, {
        position: "top-right",
        autoClose: 3000,
        hideProgressBar: false,
        closeOnClick: true,
        pauseOnHover: true,
        draggable: true,
      });
      // Redirect or clear form here
      setFormData({ 
        fullname: "", 
        username: "", 
        email: "", 
        password: "", 
        confirmPassword: "" 
      });
      setErrors({});
      setTouched({});
    }
  }, [auth, user]);

  // ─────────────────────────────────────────────
  // EVENT HANDLERS
  // ─────────────────────────────────────────────

  const handleChange = (e) => {
    const { name, value } = e.target;
    
    // Transform input based on field type
    let transformedValue = value;
    
    if (name === 'username') {
      // Convert to lowercase for username
      transformedValue = value.toLowerCase();
    } else if (name === 'email') {
      // Keep email as is but trim
      transformedValue = value.trim();
    } else if (name === 'fullname') {
      // Capitalize first letter of each word for fullname
      transformedValue = value.replace(/\b\w/g, char => char.toUpperCase());
    }
    
    setFormData(prev => ({
      ...prev,
      [name]: transformedValue
    }));

    // Validate field if it's been touched
    if (touched[name]) {
      const error = validateField(name, transformedValue);
      setErrors(prev => ({
        ...prev,
        [name]: error
      }));
    }
  };

  const handleBlur = (e) => {
    const { name, value } = e.target;
    setTouched(prev => ({
      ...prev,
      [name]: true
    }));

    const error = validateField(name, value);
    setErrors(prev => ({
      ...prev,
      [name]: error
    }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    // Mark all fields as touched
    const allTouched = {};
    Object.keys(formData).forEach(key => {
      allTouched[key] = true;
    });
    setTouched(allTouched);

    // Validate all fields
    const formErrors = validateForm();
    setErrors(formErrors);

    // Check if form is valid
    if (Object.keys(formErrors).length === 0) {
      try {
        await dispatch(signup({
          fullname: formData.fullname,
          username: formData.username,
          email: formData.email,
          password: formData.password
        })).unwrap();

      } catch (error) {
        // Error is already handled by the slice and useEffect
        console.error("Signup failed:", error);
      }
    } else {
      toast.error("Please fix the validation errors", {
        position: "top-right",
        autoClose: 3000,
      });
    }
  };

  // ─────────────────────────────────────────────
  // RENDER
  // ─────────────────────────────────────────────

  const getInputClassName = (fieldName) => {
    if (!touched[fieldName]) return "form-control";
    return errors[fieldName] ? "form-control is-invalid" : "form-control is-valid";
  };

  return (
    <div className="container-fluid vh-100 d-flex align-items-center justify-content-center bg-light">
      <div className="row w-100 justify-content-center">
        <div className="col-12 col-md-8 col-lg-6 col-xl-4">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.5 }}
            className="card shadow-lg border-0 rounded-3"
          >
            <div className="card-body p-4 p-md-5">
              <div className="text-center mb-4">
                <h2 className="card-title fw-bold text-primary">Create Account</h2>
                <p className="text-muted">Join our community today</p>
              </div>

              <form onSubmit={handleSubmit} noValidate>
                <fieldset disabled={loading}>
                  {/* Full Name */}
                  <div className="mb-3">
                    <label htmlFor="fullname" className="form-label fw-semibold">
                      Full Name
                    </label>
                    <div className="input-group">
                      <span className="input-group-text bg-light border-end-0">
                        <i className="fas fa-user text-muted"></i>
                      </span>
                      <input
                        type="text"
                        className={getInputClassName('fullname')}
                        id="fullname"
                        name="fullname"
                        placeholder="Enter your full name"
                        value={formData.fullname}
                        onChange={handleChange}
                        onBlur={handleBlur}
                        disabled={loading}
                      />
                    </div>
                    {errors.fullname && touched.fullname && (
                      <div className="invalid-feedback d-block">
                        {errors.fullname}
                      </div>
                    )}
                  </div>

                  {/* Username */}
                  <div className="mb-3">
                    <label htmlFor="username" className="form-label fw-semibold">
                      Username
                    </label>
                    <div className="input-group">
                      <span className="input-group-text bg-light border-end-0">
                        <i className="fas fa-at text-muted"></i>
                      </span>
                      <input
                        type="text"
                        className={getInputClassName('username')}
                        id="username"
                        name="username"
                        placeholder="Choose a username"
                        value={formData.username}
                        onChange={handleChange}
                        onBlur={handleBlur}
                        disabled={loading}
                      />
                    </div>
                    {errors.username && touched.username && (
                      <div className="invalid-feedback d-block">
                        {errors.username}
                      </div>
                    )}
                    <div className="form-text">
                      3-20 characters, letters, numbers, and underscores only. Must start with a letter.
                    </div>
                  </div>

                  {/* Email */}
                  <div className="mb-3">
                    <label htmlFor="email" className="form-label fw-semibold">
                      Email Address
                    </label>
                    <div className="input-group">
                      <span className="input-group-text bg-light border-end-0">
                        <i className="fas fa-envelope text-muted"></i>
                      </span>
                      <input
                        type='email'
                        className={getInputClassName('email')}
                        id="email"
                        name="email"
                        placeholder="Enter your email"
                        value={formData.email}
                        onChange={handleChange}
                        onBlur={handleBlur}
                        disabled={loading}
                      />
                    </div>
                    {errors.email && touched.email && (
                      <div className="invalid-feedback d-block">
                        {errors.email}
                      </div>
                    )}
                  </div>

                  {/* Password */}
                  <div className="mb-3">
                    <label htmlFor="password" className="form-label fw-semibold">
                      Password
                    </label>
                    <div className="input-group">
                      <span className="input-group-text bg-light border-end-0">
                        <i className="fas fa-lock text-muted"></i>
                      </span>
                      <input
                        type='password'
                        className={getInputClassName('password')}
                        id="password"
                        name="password"
                        placeholder="Create a password"
                        value={formData.password}
                        onChange={handleChange}
                        onBlur={handleBlur}
                        disabled={loading}
                      />
                    </div>
                    {errors.password && touched.password && (
                      <div className="invalid-feedback d-block">
                        {errors.password}
                      </div>
                    )}
                    <div className="form-text">
                      Minimum 6 characters with uppercase, lowercase, numbers, and special characters.
                    </div>
                  </div>

                  {/* Confirm Password */}
                  <div className="mb-4">
                    <label htmlFor="confirmPassword" className="form-label fw-semibold">
                      Confirm Password
                    </label>
                    <div className="input-group">
                      <span className="input-group-text bg-light border-end-0">
                        <i className="fas fa-lock text-muted"></i>
                      </span>
                      <input
                        type='password'
                        className={getInputClassName('confirmPassword')}
                        id="confirmPassword"
                        name="confirmPassword"
                        placeholder="Confirm your password"
                        value={formData.confirmPassword}
                        onChange={handleChange}
                        onBlur={handleBlur}
                        disabled={loading}
                      />
                    </div>
                    {errors.confirmPassword && touched.confirmPassword && (
                      <div className="invalid-feedback d-block">
                        {errors.confirmPassword}
                      </div>
                    )}
                  </div>

                  {/* Submit Button */}
                  <div className="d-grid mb-3">
                    <button 
                      type="submit" 
                      className={`btn btn-primary btn-lg fw-semibold ${loading ? 'disabled' : ''}`}
                      disabled={loading}
                    >
                      {loading ? (
                        <>
                          <span className="spinner-border spinner-border-sm me-2" role="status"></span>
                          Creating Account...
                        </>
                      ) : (
                        "Create Account"
                      )}
                    </button>
                  </div>
                </fieldset>
              </form>

              <div className="text-center">
                <p className="text-muted mb-0">
                  Already have an account?{" "}
                  <a href="/signin" className="text-primary text-decoration-none fw-semibold">
                    Sign in here
                  </a>
                </p>
              </div>
            </div>
          </motion.div>
        </div>
      </div>
    </div>
  );
}
