/**
 * CSP-Compliant Style Utilities
 * 
 * This module provides utility functions for manipulating element styles
 * in a Content Security Policy (CSP) compliant manner. Instead of directly
 * manipulating the style attribute, these utilities use CSS classes.
 * 
 * Usage:
 *   StyleUtils.addClass(element, 'my-class');
 *   StyleUtils.removeClass(element, 'my-class');
 *   StyleUtils.toggleClass(element, 'my-class');
 *   StyleUtils.hasClass(element, 'my-class');
 *   StyleUtils.replaceClass(element, 'old-class', 'new-class');
 */

(function(window) {
    'use strict';

    /**
     * StyleUtils - CSP-compliant style manipulation utilities
     */
    const StyleUtils = {
        /**
         * Add a CSS class to an element
         * @param {HTMLElement|string} element - Target element or selector
         * @param {string} className - CSS class name to add
         * @returns {boolean} True if class was added, false otherwise
         */
        addClass: function(element, className) {
            const el = this._getElement(element);
            if (!el || !className) return false;
            
            el.classList.add(className);
            return true;
        },

        /**
         * Remove a CSS class from an element
         * @param {HTMLElement|string} element - Target element or selector
         * @param {string} className - CSS class name to remove
         * @returns {boolean} True if class was removed, false otherwise
         */
        removeClass: function(element, className) {
            const el = this._getElement(element);
            if (!el || !className) return false;
            
            el.classList.remove(className);
            return true;
        },

        /**
         * Toggle a CSS class on an element
         * @param {HTMLElement|string} element - Target element or selector
         * @param {string} className - CSS class name to toggle
         * @param {boolean} [force] - Optional force parameter (true=add, false=remove)
         * @returns {boolean} True if class is now present, false otherwise
         */
        toggleClass: function(element, className, force) {
            const el = this._getElement(element);
            if (!el || !className) return false;
            
            if (force !== undefined) {
                return el.classList.toggle(className, force);
            }
            return el.classList.toggle(className);
        },

        /**
         * Check if an element has a CSS class
         * @param {HTMLElement|string} element - Target element or selector
         * @param {string} className - CSS class name to check
         * @returns {boolean} True if element has the class, false otherwise
         */
        hasClass: function(element, className) {
            const el = this._getElement(element);
            if (!el || !className) return false;
            
            return el.classList.contains(className);
        },

        /**
         * Replace one CSS class with another
         * @param {HTMLElement|string} element - Target element or selector
         * @param {string} oldClassName - CSS class name to remove
         * @param {string} newClassName - CSS class name to add
         * @returns {boolean} True if replacement succeeded, false otherwise
         */
        replaceClass: function(element, oldClassName, newClassName) {
            const el = this._getElement(element);
            if (!el || !oldClassName || !newClassName) return false;
            
            el.classList.remove(oldClassName);
            el.classList.add(newClassName);
            return true;
        },

        /**
         * Add multiple CSS classes to an element
         * @param {HTMLElement|string} element - Target element or selector
         * @param {string[]} classNames - Array of CSS class names to add
         * @returns {boolean} True if all classes were added, false otherwise
         */
        addClasses: function(element, classNames) {
            const el = this._getElement(element);
            if (!el || !Array.isArray(classNames)) return false;
            
            classNames.forEach(className => {
                if (className) el.classList.add(className);
            });
            return true;
        },

        /**
         * Remove multiple CSS classes from an element
         * @param {HTMLElement|string} element - Target element or selector
         * @param {string[]} classNames - Array of CSS class names to remove
         * @returns {boolean} True if all classes were removed, false otherwise
         */
        removeClasses: function(element, classNames) {
            const el = this._getElement(element);
            if (!el || !Array.isArray(classNames)) return false;
            
            classNames.forEach(className => {
                if (className) el.classList.remove(className);
            });
            return true;
        },

        /**
         * Show an element by removing 'hidden' or 'd-none' class
         * @param {HTMLElement|string} element - Target element or selector
         * @returns {boolean} True if element was shown, false otherwise
         */
        show: function(element) {
            const el = this._getElement(element);
            if (!el) return false;
            
            el.classList.remove('hidden', 'd-none');
            return true;
        },

        /**
         * Hide an element by adding 'd-none' class
         * @param {HTMLElement|string} element - Target element or selector
         * @returns {boolean} True if element was hidden, false otherwise
         */
        hide: function(element) {
            const el = this._getElement(element);
            if (!el) return false;
            
            el.classList.add('d-none');
            return true;
        },

        /**
         * Toggle visibility of an element
         * @param {HTMLElement|string} element - Target element or selector
         * @returns {boolean} True if element is now visible, false if hidden
         */
        toggleVisibility: function(element) {
            const el = this._getElement(element);
            if (!el) return false;
            
            const isHidden = el.classList.contains('d-none') || el.classList.contains('hidden');
            if (isHidden) {
                this.show(el);
                return true;
            } else {
                this.hide(el);
                return false;
            }
        },

        /**
         * Set validation state on a form field
         * @param {HTMLElement|string} element - Target form field or selector
         * @param {string} state - Validation state: 'valid', 'invalid', or 'none'
         * @returns {boolean} True if state was set, false otherwise
         */
        setValidationState: function(element, state) {
            const el = this._getElement(element);
            if (!el) return false;
            
            // Remove all validation classes
            el.classList.remove('is-valid', 'is-invalid');
            
            // Add appropriate class based on state
            if (state === 'valid') {
                el.classList.add('is-valid');
            } else if (state === 'invalid') {
                el.classList.add('is-invalid');
            }
            
            return true;
        },

        /**
         * Set loading state on an element
         * @param {HTMLElement|string} element - Target element or selector
         * @param {boolean} isLoading - True to show loading, false to hide
         * @returns {boolean} True if state was set, false otherwise
         */
        setLoadingState: function(element, isLoading) {
            const el = this._getElement(element);
            if (!el) return false;
            
            if (isLoading) {
                el.classList.add('loading');
                el.setAttribute('disabled', 'disabled');
            } else {
                el.classList.remove('loading');
                el.removeAttribute('disabled');
            }
            
            return true;
        },

        /**
         * Set active state on an element
         * @param {HTMLElement|string} element - Target element or selector
         * @param {boolean} isActive - True to set active, false to remove
         * @returns {boolean} True if state was set, false otherwise
         */
        setActiveState: function(element, isActive) {
            const el = this._getElement(element);
            if (!el) return false;
            
            if (isActive) {
                el.classList.add('active');
            } else {
                el.classList.remove('active');
            }
            
            return true;
        },

        /**
         * Set disabled state on an element
         * @param {HTMLElement|string} element - Target element or selector
         * @param {boolean} isDisabled - True to disable, false to enable
         * @returns {boolean} True if state was set, false otherwise
         */
        setDisabledState: function(element, isDisabled) {
            const el = this._getElement(element);
            if (!el) return false;
            
            if (isDisabled) {
                el.classList.add('disabled');
                el.setAttribute('disabled', 'disabled');
            } else {
                el.classList.remove('disabled');
                el.removeAttribute('disabled');
            }
            
            return true;
        },

        /**
         * Helper method to get element from selector or element
         * @private
         * @param {HTMLElement|string} element - Element or selector
         * @returns {HTMLElement|null} The element or null if not found
         */
        _getElement: function(element) {
            if (typeof element === 'string') {
                return document.querySelector(element);
            }
            return element instanceof HTMLElement ? element : null;
        }
    };

    /**
     * FormUtils - Utilities for form manipulation
     */
    const FormUtils = {
        /**
         * Show validation error on a form field
         * @param {HTMLElement|string} field - Form field element or selector
         * @param {string} message - Error message to display
         * @returns {boolean} True if error was shown, false otherwise
         */
        showFieldError: function(field, message) {
            const el = StyleUtils._getElement(field);
            if (!el) return false;
            
            // Set invalid state
            StyleUtils.setValidationState(el, 'invalid');
            
            // Find or create error message element
            let errorEl = el.parentElement.querySelector('.invalid-feedback');
            if (!errorEl) {
                errorEl = document.createElement('div');
                errorEl.className = 'invalid-feedback';
                el.parentElement.appendChild(errorEl);
            }
            
            errorEl.textContent = message;
            StyleUtils.show(errorEl);
            
            return true;
        },

        /**
         * Clear validation error from a form field
         * @param {HTMLElement|string} field - Form field element or selector
         * @returns {boolean} True if error was cleared, false otherwise
         */
        clearFieldError: function(field) {
            const el = StyleUtils._getElement(field);
            if (!el) return false;
            
            // Remove invalid state
            StyleUtils.setValidationState(el, 'none');
            
            // Hide error message
            const errorEl = el.parentElement.querySelector('.invalid-feedback');
            if (errorEl) {
                StyleUtils.hide(errorEl);
            }
            
            return true;
        },

        /**
         * Show validation success on a form field
         * @param {HTMLElement|string} field - Form field element or selector
         * @returns {boolean} True if success was shown, false otherwise
         */
        showFieldSuccess: function(field) {
            const el = StyleUtils._getElement(field);
            if (!el) return false;
            
            StyleUtils.setValidationState(el, 'valid');
            return true;
        },

        /**
         * Reset form field validation state
         * @param {HTMLElement|string} field - Form field element or selector
         * @returns {boolean} True if state was reset, false otherwise
         */
        resetFieldValidation: function(field) {
            const el = StyleUtils._getElement(field);
            if (!el) return false;
            
            this.clearFieldError(el);
            StyleUtils.setValidationState(el, 'none');
            return true;
        }
    };

    /**
     * AnimationUtils - Utilities for CSS-based animations
     */
    const AnimationUtils = {
        /**
         * Fade in an element using CSS classes
         * @param {HTMLElement|string} element - Target element or selector
         * @param {number} [duration=300] - Animation duration in milliseconds
         * @returns {Promise} Promise that resolves when animation completes
         */
        fadeIn: function(element, duration = 300) {
            const el = StyleUtils._getElement(element);
            if (!el) return Promise.reject(new Error('Element not found'));
            
            return new Promise((resolve) => {
                StyleUtils.removeClass(el, 'd-none');
                StyleUtils.addClass(el, 'fade-in');
                
                setTimeout(() => {
                    StyleUtils.removeClass(el, 'fade-in');
                    resolve();
                }, duration);
            });
        },

        /**
         * Fade out an element using CSS classes
         * @param {HTMLElement|string} element - Target element or selector
         * @param {number} [duration=300] - Animation duration in milliseconds
         * @returns {Promise} Promise that resolves when animation completes
         */
        fadeOut: function(element, duration = 300) {
            const el = StyleUtils._getElement(element);
            if (!el) return Promise.reject(new Error('Element not found'));
            
            return new Promise((resolve) => {
                StyleUtils.addClass(el, 'fade-out');
                
                setTimeout(() => {
                    StyleUtils.removeClass(el, 'fade-out');
                    StyleUtils.addClass(el, 'd-none');
                    resolve();
                }, duration);
            });
        }
    };

    // Export to global scope
    window.StyleUtils = StyleUtils;
    window.FormUtils = FormUtils;
    window.AnimationUtils = AnimationUtils;

    // Also support CommonJS/AMD if available
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = { StyleUtils, FormUtils, AnimationUtils };
    }

})(window);
