import React, { useState, useEffect } from "react";
import { useDispatch, useSelector } from "react-redux";
import { useNavigate, Link } from "react-router-dom";
import { motion } from "framer-motion";
import { toast } from "react-toastify";
import { forgotPassword } from "../store/authSlice";
import "./authStyle.css"

export default function ForgetPassword() {
  const [formData, setFormData] = useState({
    email: ""
  });
  const [errors, setErrors] = useState({});
  const [touched, setTouched] = useState({});
  const [isSubmitted, setIsSubmitted] = useState(false);
  
  const dispatch = useDispatch();
  const navigate = useNavigate();
  const { resError, loading, res } = useSelector((state) => state.auth);

  // ─────────────────────────────────────────────
  // VALIDATION RULES
  // ─────────────────────────────────────────────

  const validationRules = {
    email: (value) => {
      if (!value) return "Email is required";
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(value)) return "Please enter a valid email address";
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
    if (res && isSubmitted) {
      toast.success("Password reset instructions sent to your email!", {
        position: "top-right",
        autoClose: 5000,
        hideProgressBar: false,
        closeOnClick: true,
        pauseOnHover: true,
        draggable: true,
      });
      // Don't navigate immediately, let user see the success message
    }
  }, [res, isSubmitted]);

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
    setIsSubmitted(true);
    
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
        await dispatch(forgotPassword(formData.email)).unwrap();
        
        // Success is handled in useEffect
      } catch (error) {
        // Error is already handled by the slice and useEffect
        console.error("Forgot password request failed:", error);
      }
    } else {
      toast.error("Please fix the validation errors", {
        position: "top-right",
        autoClose: 3000,
      });
    }
  };

  const handleBackToLogin = () => {
    navigate('/signin');
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
              
              {/* Success State */}
              {res && isSubmitted ? (
                <div className="text-center">
                  <div className="mb-4">
                    <i className="fas fa-envelope-circle-check text-success" style={{ fontSize: '4rem' }}></i>
                  </div>
                  <h2 className="card-title fw-bold text-success mb-3">Check Your Email</h2>
                  <p className="text-muted mb-4">
                    We've sent password reset instructions to:<br />
                    <strong className="text-dark">{formData.email}</strong>
                  </p>
                  <div className="alert alert-info mb-4">
                    <small>
                      <i className="fas fa-info-circle me-2"></i>
                      If you don't see the email, check your spam folder or try again.
                    </small>
                  </div>
                  <div className="d-grid gap-2">
                    <button 
                      type="button" 
                      className="btn btn-primary"
                      onClick={() => {
                        setFormData({ email: "" });
                        setIsSubmitted(false);
                        setErrors({});
                        setTouched({});
                      }}
                    >
                      <i className="fas fa-redo me-2"></i>
                      Try Another Email
                    </button>
                    <button 
                      type="button" 
                      className="btn btn-outline-secondary"
                      onClick={handleBackToLogin}
                    >
                      <i className="fas fa-arrow-left me-2"></i>
                      Back to Sign In
                    </button>
                  </div>
                </div>
              ) : (
                /* Form State */
                <>
                  <div className="text-center mb-4">
                    <div className="mb-3">
                      <i className="fas fa-key text-primary" style={{ fontSize: '3rem' }}></i>
                    </div>
                    <h2 className="card-title fw-bold text-primary mb-2">Reset Your Password</h2>
                    <p className="text-muted">
                      Enter your email address and we'll send you instructions to reset your password.
                    </p>
                  </div>

                  <form onSubmit={handleSubmit} noValidate>
                    <fieldset disabled={loading}>
                      {/* Email */}
                      <div className="mb-4">
                        <label htmlFor="email" className="form-label fw-semibold">
                          Email Address
                        </label>
                        <div className="input-group">
                          <span className="input-group-text bg-light border-end-0">
                            <i className="fas fa-envelope text-muted"></i>
                          </span>
                          <input
                            type="email"
                            className={getInputClassName('email')}
                            id="email"
                            name="email"
                            placeholder="Enter your email address"
                            value={formData.email}
                            onChange={handleChange}
                            onBlur={handleBlur}
                            disabled={loading}
                            autoComplete="email"
                          />
                        </div>
                        {errors.email && touched.email && (
                          <div className="invalid-feedback d-block">
                            {errors.email}
                          </div>
                        )}
                        <div className="form-text">
                          Enter the email address associated with your account.
                        </div>
                      </div>

                      {/* Submit Button */}
                      <div className="d-grid mb-4">
                        <button 
                          type="submit" 
                          className={`btn btn-primary btn-lg fw-semibold ${loading ? 'disabled' : ''}`}
                          disabled={loading}
                        >
                          {loading ? (
                            <>
                              <span className="spinner-border spinner-border-sm me-2" role="status"></span>
                              Sending Instructions...
                            </>
                          ) : (
                            <>
                              <i className="fas fa-paper-plane me-2"></i>
                              Send Reset Instructions
                            </>
                          )}
                        </button>
                      </div>
                    </fieldset>
                  </form>

                  <div className="text-center">
                    <p className="text-muted mb-2">
                      Remember your password?{" "}
                      <Link to="/signin" className="text-primary text-decoration-none fw-semibold">
                        Sign in here
                      </Link>
                    </p>
                    <p className="text-muted mb-0">
                      Don't have an account?{" "}
                      <Link to="/signup" className="text-primary text-decoration-none fw-semibold">
                        Sign up here
                      </Link>
                    </p>
                  </div>
                </>
              )}
            </div>
          </motion.div>
        </div>
      </div>
    </div>
  );
}
