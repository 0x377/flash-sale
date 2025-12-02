import { useState, useEffect } from "react";
import { motion } from "framer-motion";
import { useDispatch, useSelector } from "react-redux";
import { useNavigate, Link } from "react-router-dom";
import { toast } from "react-toastify";
import { signin } from "../store/authSlice";
import "./authStyle.css"

export default function Signin() {
  const [formData, setFormData] = useState({
    email: "",
    password: ""
  });
  const [errors, setErrors] = useState({});
  const [touched, setTouched] = useState({});

  const dispatch = useDispatch();
  const navigate = useNavigate();
  const { user, auth, resError, loading } = useSelector((state) => state.auth);

  // ─────────────────────────────────────────────
  // VALIDATION RULES
  // ─────────────────────────────────────────────

  const validationRules = {
    email: (value) => {
      if (!value) return "Email is required";
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(value)) return "Please enter a valid email address";
      return null;
    },
    password: (value) => {
      if (!value) return "Password is required";
      if (value.length < 6) return "Password must be at least 6 characters";
      if (!/(?=.*\d)/.test(value)) return "Password must contain at least one number";
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
    if (auth && user) {
      toast.success(`Welcome back, ${user.fullname || user.username}!`, {
        position: "top-right",
        autoClose: 3000,
        hideProgressBar: false,
        closeOnClick: true,
        pauseOnHover: true,
        draggable: true,
      });
      navigate('/dashboard'); // Redirect to dashboard
    }
  }, [auth, user, navigate]);

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
        await dispatch(signin({
          email: formData.email,
          password: formData.password
        })).unwrap();

        // Success is handled in useEffect
      } catch (error) {
        // Error is already handled by the slice and useEffect
        console.error("Signin failed:", error);
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
                <div className="mb-3">
                  <i className="fas fa-sign-in-alt text-primary" style={{ fontSize: '3rem' }}></i>
                </div>
                <h2 className="card-title fw-bold text-primary mb-2">Welcome Back</h2>
                <p className="text-muted">Sign in to your account to continue</p>
              </div>

              <form onSubmit={handleSubmit} noValidate>
                <fieldset disabled={loading}>
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
                        type="email"
                        className={getInputClassName('email')}
                        id="email"
                        name="email"
                        placeholder="Enter your email"
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
                        type="password"
                        className={getInputClassName('password')}
                        id="password"
                        name="password"
                        placeholder="Enter your password"
                        value={formData.password}
                        onChange={handleChange}
                        onBlur={handleBlur}
                        disabled={loading}
                        autoComplete="current-password"
                      />
                    </div>
                    {errors.password && touched.password && (
                      <div className="invalid-feedback d-block">
                        {errors.password}
                      </div>
                    )}
                    <div className="form-text">
                      Password must be at least 6 characters with numbers.
                    </div>
                  </div>

                  {/* Remember Me & Forgot Password */}
                  <div className="d-flex justify-content-between align-items-center mb-4">
                    <div className="form-check">
                      <input
                        type="checkbox"
                        className="form-check-input"
                        id="rememberMe"
                      />
                      <label className="form-check-label" htmlFor="rememberMe">
                        Remember me
                      </label>
                    </div>
                    <Link 
                      to="/forgot-password" 
                      className="text-primary text-decoration-none fw-semibold"
                    >
                      Forgot password?
                    </Link>
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
                          Signing In...
                        </>
                      ) : (
                        "Sign In"
                      )}
                    </button>
                  </div>

                  {/* Social Login Options */}
                  <div className="text-center mb-4">
                    <div className="position-relative">
                      <hr />
                      <span className="position-absolute top-50 start-50 translate-middle bg-white px-3 text-muted">
                        Or continue with
                      </span>
                    </div>
                    <div className="d-grid gap-2 d-sm-flex justify-content-sm-center mt-3">
                      <button type="button" className="btn btn-outline-primary btn-sm">
                        <i className="fab fa-google me-2"></i>Google
                      </button>
                      <button type="button" className="btn btn-outline-dark btn-sm">
                        <i className="fab fa-github me-2"></i>GitHub
                      </button>
                    </div>
                  </div>
                </fieldset>
              </form>

              <div className="text-center">
                <p className="text-muted mb-0">
                  Don't have an account?{" "}
                  <Link to="/signup" className="text-primary text-decoration-none fw-semibold">
                    Sign up here
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
