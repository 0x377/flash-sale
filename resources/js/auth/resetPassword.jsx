import React, { useState, useEffect } from "react";
import { useDispatch, useSelector } from "react-redux";
import { useNavigate, useParams, Link } from "react-router-dom";
import { motion } from "framer-motion";
import { toast } from "react-toastify";
import { resetPassword } from "../store/authSlice";
import "./authStyle.css"

export default function ResetPassword() {
  const [formData, setFormData] = useState({
    password: "",
    confirmPassword: ""
  });
  const [errors, setErrors] = useState({});
  const [touched, setTouched] = useState({});
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);
  
  const dispatch = useDispatch();
  const navigate = useNavigate();
  const { token } = useParams(); // Get token from URL params
  const { resError, loading, res } = useSelector((state) => state.auth);

  // ─────────────────────────────────────────────
  // VALIDATION RULES
  // ─────────────────────────────────────────────

  const validationRules = {
    password: (value) => {
      if (!value) return "Password is required";
      if (value.length < 8) return "Password must be at least 8 characters";
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
    if (res) {
      toast.success("Password reset successfully! You can now sign in with your new password.", {
        position: "top-right",
        autoClose: 3000,
        hideProgressBar: false,
        closeOnClick: true,
        pauseOnHover: true,
        draggable: true,
      });
      navigate('/signin');
    }
  }, [res, navigate]);

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
  // EVENT HANDLERS
  // ─────────────────────────────────────────────

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: value
    }));

    // Validate field if it's been touched
    if (touched[name]) {
      const error = validateField(name, value);
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
        await dispatch(resetPassword({
          token, // Token from URL params
          password: formData.password
        })).unwrap();

        // Success is handled in useEffect
      } catch (error) {
        // Error is already handled by the slice and useEffect
        console.error("Password reset failed:", error);
      }
    } else {
      toast.error("Please fix the validation errors", {
        position: "top-right",
        autoClose: 3000,
      });
    }
  };

  const togglePasswordVisibility = () => {
    setShowPassword(!showPassword);
  };

  const toggleConfirmPasswordVisibility = () => {
    setShowConfirmPassword(!showConfirmPassword);
  };

  // ─────────────────────────────────────────────
  // RENDER
  // ─────────────────────────────────────────────

  const getInputClassName = (fieldName) => {
    if (!touched[fieldName]) return "form-control";
    return errors[fieldName] ? "form-control is-invalid" : "form-control is-valid";
  };

  const getPasswordStrength = (password) => {
    if (!password) return { strength: "", color: "" };
    
    let score = 0;
    if (password.length >= 8) score++;
    if (/[a-z]/.test(password)) score++;
    if (/[A-Z]/.test(password)) score++;
    if (/\d/.test(password)) score++;
    if (/[!@#$%^&*()_+\-=[\]{};':"\\|,.<>/?]/.test(password)) score++;

    const strengths = [
      { strength: "Very Weak", color: "danger" },
      { strength: "Weak", color: "warning" },
      { strength: "Fair", color: "info" },
      { strength: "Good", color: "primary" },
      { strength: "Strong", color: "success" }
    ];

    return strengths[score] || strengths[0];
  };

  const passwordStrength = getPasswordStrength(formData.password);

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
                <div className="mb-3">
                  <i className="fas fa-key text-primary" style={{ fontSize: '3rem' }}></i>
                </div>
                <h2 className="card-title fw-bold text-primary mb-2">Reset Password</h2>
                <p className="text-muted">Create a new strong password for your account</p>
              </div>

              <form onSubmit={handleSubmit} noValidate>
                <fieldset disabled={loading}>
                  {/* Password */}
                  <div className="mb-3">
                    <label htmlFor="password" className="form-label fw-semibold">
                      New Password
                    </label>
                    <div className="input-group">
                      <span className="input-group-text bg-light border-end-0">
                        <i className="fas fa-lock text-muted"></i>
                      </span>
                      <input
                        type={showPassword ? "text" : "password"}
                        className={getInputClassName('password')}
                        id="password"
                        name="password"
                        placeholder="Enter new password"
                        value={formData.password}
                        onChange={handleChange}
                        onBlur={handleBlur}
                        disabled={loading}
                        autoComplete="new-password"
                      />
                      <button
                        type="button"
                        className="btn btn-outline-secondary border-start-0"
                        onClick={togglePasswordVisibility}
                      >
                        <i className={`fas ${showPassword ? 'fa-eye-slash' : 'fa-eye'}`}></i>
                      </button>
                    </div>
                    {errors.password && touched.password && (
                      <div className="invalid-feedback d-block">
                        {errors.password}
                      </div>
                    )}
                    
                    {/* Password Strength Indicator */}
                    {formData.password && (
                      <div className="mt-2">
                        <div className="d-flex justify-content-between align-items-center mb-1">
                          <small className="text-muted">Password Strength:</small>
                          <small className={`text-${passwordStrength.color} fw-semibold`}>
                            {passwordStrength.strength}
                          </small>
                        </div>
                        <div className="progress" style={{ height: '4px' }}>
                          <div 
                            className={`progress-bar bg-${passwordStrength.color}`}
                            style={{ width: `${(passwordStrength.strength === "Very Weak" ? 20 : 
                                              passwordStrength.strength === "Weak" ? 40 :
                                              passwordStrength.strength === "Fair" ? 60 :
                                              passwordStrength.strength === "Good" ? 80 : 100)}%` }}
                          ></div>
                        </div>
                      </div>
                    )}
                    
                    <div className="form-text">
                      Must be at least 8 characters with uppercase, lowercase, numbers, and special characters.
                    </div>
                  </div>

                  {/* Confirm Password */}
                  <div className="mb-4">
                    <label htmlFor="confirmPassword" className="form-label fw-semibold">
                      Confirm New Password
                    </label>
                    <div className="input-group">
                      <span className="input-group-text bg-light border-end-0">
                        <i className="fas fa-lock text-muted"></i>
                      </span>
                      <input
                        type={showConfirmPassword ? "text" : "password"}
                        className={getInputClassName('confirmPassword')}
                        id="confirmPassword"
                        name="confirmPassword"
                        placeholder="Confirm new password"
                        value={formData.confirmPassword}
                        onChange={handleChange}
                        onBlur={handleBlur}
                        disabled={loading}
                        autoComplete="new-password"
                      />
                      <button
                        type="button"
                        className="btn btn-outline-secondary border-start-0"
                        onClick={toggleConfirmPasswordVisibility}
                      >
                        <i className={`fas ${showConfirmPassword ? 'fa-eye-slash' : 'fa-eye'}`}></i>
                      </button>
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
                          Resetting Password...
                        </>
                      ) : (
                        "Reset Password"
                      )}
                    </button>
                  </div>
                </fieldset>
              </form>

              <div className="text-center">
                <p className="text-muted mb-0">
                  Remember your password?{" "}
                  <Link to="/signin" className="text-primary text-decoration-none fw-semibold">
                    Sign in here
                  </Link>
                </p>
              </div>
            </div>
          </motion.div>
        </div>
      </div>
    </div>
  );
}
