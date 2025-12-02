import { useRef, useState, useEffect } from "react";
import { useDispatch, useSelector } from "react-redux";
import { useNavigate } from "react-router-dom";
import { motion } from "framer-motion";
import { toast } from "react-toastify";
import { verifyEmail } from "../store/authSlice";
import "./authStyle.css"

export default function VerifyEmail() {
  const [code, setCode] = useState(['', '', '', '', '', '']);
  const [errors, setErrors] = useState({});
  const [touched, setTouched] = useState(false);
  const inputRefs = useRef([]);
  
  const dispatch = useDispatch();
  const navigate = useNavigate();
  const { user, auth, resError, loading } = useSelector((state) => state.auth);

  // ─────────────────────────────────────────────
  // VALIDATION RULES
  // ─────────────────────────────────────────────

  const validationRules = {
    code: (value) => {
      const codeString = value.join('');
      
      if (codeString.length !== 6) return "Please enter all 6 digits";
      if (!/^\d+$/.test(codeString)) return "Code must contain only numbers";
      if (!/^[0-9]{6}$/.test(codeString)) return "Code must be exactly 6 digits";
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
      toast.success(`Email verified successfully! Welcome ${user.fullname || user.username}!`, {
        position: "top-right",
        autoClose: 3000,
        hideProgressBar: false,
        closeOnClick: true,
        pauseOnHover: true,
        draggable: true,
      });
      navigate('/dashboard'); // Redirect to dashboard or preferred route
    }
  }, [auth, user, navigate]);

  // ─────────────────────────────────────────────
  // EVENT HANDLERS
  // ─────────────────────────────────────────────

  const handleChange = (index, value) => {
    const newCode = [...code];
    
    // Allow only numbers
    const numericValue = value.replace(/[^0-9]/g, '');

    if (numericValue.length > 1) {
      // Handle paste event
      const pastedCode = numericValue.slice(0, 6).split('');
      for (let i = 0; i < 6; i++) {
        newCode[i] = pastedCode[i] || '';
      }
      setCode(newCode);

      // Focus on the next empty field or last field
      const lastFilledIndex = newCode.findLastIndex((digit) => digit !== '');
      const focusIndex = lastFilledIndex < 5 ? lastFilledIndex + 1 : 5;
      inputRefs.current[focusIndex]?.focus();
    } else {
      // Handle single digit input
      newCode[index] = numericValue;
      setCode(newCode);

      // Auto-focus next input if value entered
      if (numericValue && index < 5) {
        inputRefs.current[index + 1]?.focus();
      }
    }

    // Validate on change if form has been touched
    if (touched) {
      validateCode(newCode);
    }
  };

  const handleKeyDown = (index, e) => {
    if (e.key === 'Backspace') {
      if (!code[index] && index > 0) {
        // Move to previous input on backspace if current is empty
        inputRefs.current[index - 1]?.focus();
      } else if (code[index]) {
        // Clear current input and stay there
        const newCode = [...code];
        newCode[index] = '';
        setCode(newCode);
      }
    } else if (e.key === 'ArrowLeft' && index > 0) {
      // Move left with arrow key
      inputRefs.current[index - 1]?.focus();
    } else if (e.key === 'ArrowRight' && index < 5) {
      // Move right with arrow key
      inputRefs.current[index + 1]?.focus();
    } else if (e.key === 'Enter') {
      // Submit on Enter key
      handleSubmit(e);
    }
  };

  const handlePaste = (e) => {
    e.preventDefault();
    const pastedData = e.clipboardData.getData('text');
    if (pastedData) {
      const firstInput = inputRefs.current[0];
      if (firstInput) {
        handleChange(0, pastedData);
      }
    }
  };

  const validateCode = (currentCode = code) => {
    const error = validationRules.code(currentCode);
    setErrors(prev => ({
      ...prev,
      code: error
    }));
    return !error;
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setTouched(true);

    const isValid = validateCode();
    
    if (!isValid) {
      toast.error("Please fix the validation errors", {
        position: "top-right",
        autoClose: 3000,
      });
      return;
    }

    try {
      const verificationCode = code.join('');
      await dispatch(verifyEmail(verificationCode)).unwrap();
      
      // Success is handled in useEffect
    } catch (error) {
      // Error is already handled by the slice and useEffect
      console.error("Verification failed:", error);
    }
  };

  const handleResendCode = async () => {
    toast.info("Resending verification code...", {
      position: "top-right",
      autoClose: 3000,
    });
    // Add resend code logic here if available
  };

  // ─────────────────────────────────────────────
  // RENDER
  // ─────────────────────────────────────────────

  const getInputClassName = (index) => {
    const baseClass = "form-control text-center fw-bold fs-5";
    if (!touched) return baseClass;
    return errors.code ? `${baseClass} is-invalid` : `${baseClass} is-valid`;
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
                  <i className="fas fa-envelope-circle-check text-primary" style={{ fontSize: '3rem' }}></i>
                </div>
                <h2 className="card-title fw-bold text-primary mb-2">Verify Your Email</h2>
                <p className="text-muted mb-0">
                  Enter the 6-digit verification code sent to your email address
                </p>
              </div>

              <form onSubmit={handleSubmit} noValidate>
                <fieldset disabled={loading}>
                  {/* Code Inputs */}
                  <div className="mb-4">
                    <label className="form-label fw-semibold text-center w-100">
                      Verification Code
                    </label>
                    <div className="d-flex justify-content-between gap-2 mb-3">
                      {code.map((digit, index) => (
                        <input
                          key={index}
                          ref={(el) => { inputRefs.current[index] = el }}
                          type="text"
                          inputMode="numeric"
                          pattern="[0-9]*"
                          maxLength="1"
                          className={getInputClassName(index)}
                          style={{
                            height: '60px',
                            width: '50px',
                            fontSize: '1.5rem',
                            border: errors.code ? '2px solid #dc3545' : '1px solid #dee2e6'
                          }}
                          value={digit}
                          onChange={(e) => handleChange(index, e.target.value)}
                          onKeyDown={(e) => handleKeyDown(index, e)}
                          onPaste={index === 0 ? handlePaste : undefined}
                          onFocus={(e) => e.target.select()}
                          disabled={loading}
                          autoComplete="one-time-code"
                        />
                      ))}
                    </div>
                    {errors.code && (
                      <div className="invalid-feedback d-block text-center">
                        {errors.code}
                      </div>
                    )}
                    <div className="form-text text-center">
                      Enter the 6-digit code from your email
                    </div>
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
                          Verifying...
                        </>
                      ) : (
                        "Verify Email"
                      )}
                    </button>
                  </div>

                  {/* Resend Code */}
                  <div className="text-center mb-3">
                    <button 
                      type="button" 
                      className="btn btn-link text-decoration-none"
                      onClick={handleResendCode}
                      disabled={loading}
                    >
                      Didn't receive the code? <span className="text-primary fw-semibold">Resend</span>
                    </button>
                  </div>
                </fieldset>
              </form>

              {/* Support Links */}
              <div className="text-center">
                <p className="text-muted mb-2">
                  Having trouble? <a href="/support" className="text-primary text-decoration-none">Contact Support</a>
                </p>
                <p className="text-muted mb-0">
                  <a href="/signin" className="text-primary text-decoration-none">
                    ← Back to Sign In
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
