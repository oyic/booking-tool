jQuery(document).ready(function($) {
    'use strict';

    class BookingWizard {
        constructor() {
            this.currentStep = 1;
            this.state = {
                service: null,
                pricePerPage: 0,
                words: 250,
                normPages: 0,
                delivery: '2d',
                extras: [],
                totals: {
                    base: 0,
                    extras: 0,
                    surcharge: 0,
                    total: 0
                },
                deliveryDate: '',
                customer: {
                    name: '',
                    email: '',
                    country: '',
                    program: '',
                    note: ''
                },
                consent: false,
                fileUploaded: false,
                voucher: {
                    code: '',
                    discount: 0,
                    applied: false
                }
            };

            this.$form = $('#lm-booking-form');
            this.$steps = $('.lm-step');
            this.$stepperItems = $('.lm-stepper-item');
            this.$cta = $('#lm-cta-primary');
            this.$errorMsg = $('.lm-error');
            this.$summaryConsent = $('#lm-summary-consent');
            this.$summaryVoucher = $('#lm-summary-voucher');

            this.init();
        }

        init() {
            this.bindEvents();
            
            // Ensure step 1 is visible by default
            this.showStep(1);
            
            // Set default selections from first available package
            const $firstCard = $('.lm-package-card').first();
            if ($firstCard.length) {
                this.state.service = $firstCard.data('service');
                this.state.pricePerPage = parseFloat($firstCard.data('price'));
                this.state.selectedPackageIndex = 0;
            } else {
                // Fallback defaults
                this.state.service = 'Premium-Paket';
                this.state.pricePerPage = 5.99;
                this.state.selectedPackageIndex = 0;
            }
            
            // Initialize package-inclusive extras for the first package
            this.updatePackageInclusiveExtras(this.state.selectedPackageIndex);
            
            // Set default delivery selection visual state
            $('.lm-pill-input[value="2d"]').prop('checked', true);
            $('.lm-pill').removeClass('lm-pill-selected');
            $('.lm-pill-input[value="2d"]').closest('.lm-pill').addClass('lm-pill-selected');
            
            this.updateState();
            this.updateSummary();
            this.updateStepper();
        }

        bindEvents() {

            // Word count slider and input interactions
            $('#lm-words-slider').on('input change', (e) => {
                const value = parseInt($(e.target).val());
                // Ensure minimum value of 250
                const finalValue = Math.max(250, value);
                $('#lm-words').val(finalValue);
                this.state.words = finalValue;
                this.updateState();
            });

            $('#lm-words').on('input', (e) => {
                const value = parseInt($(e.target).val()) || 0;
                this.state.words = value;
                this.updateState();
            });

            // Validate on blur - allow free input but validate when user leaves field
            $('#lm-words').on('blur', (e) => {
                const value = parseInt($(e.target).val()) || 0;
                let finalValue = value;
                
                // Apply minimum validation only on blur
                if (value < 250) {
                    finalValue = 250;
                    $(e.target).val(finalValue);
                }
                
                // Update slider to match final value
                $('#lm-words-slider').val(finalValue);
                this.state.words = finalValue;
                this.updateState();
            });

            // Step 1: Package selection
            $('.lm-package-card').on('click', (e) => {
                const $card = $(e.currentTarget);
                $('.lm-package-card').removeClass('lm-card-selected');
                $card.addClass('lm-card-selected');
                this.state.service = $card.data('service');
                this.state.pricePerPage = parseFloat($card.data('price'));
                
                // Get the package index from the card
                const packageIndex = $card.index();
                this.state.selectedPackageIndex = packageIndex;
                
                // Auto-select package-inclusive extras
                this.updatePackageInclusiveExtras(packageIndex);
                
                this.updateState();
            });

            // Step 1: Delivery selection
            $('.lm-pill-input').on('change', (e) => {
                const delivery = $(e.target).val();
                this.state.delivery = delivery;
                $('.lm-pill').removeClass('lm-pill-selected');
                $(e.target).closest('.lm-pill').addClass('lm-pill-selected');
                this.updateState();
            });

            // Words slider and input are now read-only (updated only by file upload)

            // Step 2: Extras selection
            $('.lm-extras-checkbox').on('change', (e) => {
                const $checkbox = $(e.target);
                const extra = JSON.parse($checkbox.val());
                
                // Don't allow unchecking package-inclusive extras
                if ($checkbox.hasClass('package-inclusive') && !$checkbox.is(':checked')) {
                    $checkbox.prop('checked', true);
                    return;
                }
                
                if ($checkbox.is(':checked')) {
                    this.state.extras.push(extra);
                } else {
                    this.state.extras = this.state.extras.filter(e => e.label !== extra.label);
                }
                this.updateState();
            });

            // Step 3: Customer form
            $('#lm-name, #lm-email, #lm-country, #lm-program, #lm-note').on('input change', (e) => {
                const field = e.target.id.replace('lm-', '');
                this.state.customer[field] = e.target.value;
                this.updateState();
            });

            // Consent checkbox
            $('#lm-consent-checkbox').on('change', (e) => {
                this.state.consent = e.target.checked;
                this.updateState();
            });

            // CTA button - Handle step navigation
            this.$cta.on('click', (e) => {
                e.preventDefault();
                this.nextStep();
            });

            // File upload handler - ONLY for word counting
            $(document).on('change', '#lm-upload-input', (e) => {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                
                const file = e.target.files[0];
                if (!file) {
                    // Reset state if no file selected
                    this.state.fileUploaded = false;
                    $('#lm-document-hidden').val('');
                    this.resetUploadButton();
                    return false;
                }

                // Clear any previous errors
                this.hideError();
                this.hideFileUploadError();

                // Validate file type
                const allowedTypes = [
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.oasis.opendocument.text',
                    'application/rtf',
                    'text/rtf'
                ];
                
                if (!allowedTypes.includes(file.type)) {
                    this.showError('Bitte wählen Sie eine gültige Datei (DOC, DOCX, ODT oder RTF).', true);
                    e.target.value = '';
                    this.state.fileUploaded = false;
                    $('#lm-document-hidden').val('');
                    this.resetUploadButton();
                    return false;
                }
                
                // Validate file size
                if (file.size > 10 * 1024 * 1024) {
                    this.showError('Die Datei ist zu groß. Maximale Größe: 10MB.', true);
                    e.target.value = '';
                    this.state.fileUploaded = false;
                    $('#lm-document-hidden').val('');
                    this.resetUploadButton();
                    return false;
                }
                
                // Update button text
                const fileName = file.name.length > 30 ? file.name.substring(0, 30) + '...' : file.name;
                $('.lm-upload-btn').html(`
                    <span class="lm-upload-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                        </svg>
                    </span>
                    ${fileName}
                `);
                
                // Mark file as uploaded
                this.state.fileUploaded = true;
                
                // Store file data for form submission
                $('#lm-document-hidden').val(file.name);
                
                // File uploaded successfully
                this.handleFileUpload(file);
                
                return false;
            });

            // Info buttons
            $(document).on('click', '.lm-extras-info', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.toggleInfoPopup($(e.currentTarget));
            });

            // Close popup when clicking outside
            $(document).on('click', (e) => {
                if (!$(e.target).closest('.lm-extras-info, .lm-info-popup').length) {
                    this.hideAllInfoPopups();
                }
            });

            // Voucher functionality
            $('#lm-apply-voucher').on('click', (e) => {
                e.preventDefault();
                this.applyVoucher();
            });

            $('#lm-voucher-code').on('keypress', (e) => {
                if (e.which === 13) { // Enter key
                    e.preventDefault();
                    this.applyVoucher();
                }
            });
        }

        calculateNormPages(words) {
            // If words is less than 250, treat as 1 page
            if (words < 250) {
                return 1;
            }
            return Math.ceil(words / 250);
        }

        formatCurrency(amount) {
            // Format number with commas as decimal separator
            return amount.toFixed(2).replace('.', ',');
        }

        calculateDeliveryDate(delivery, buffer24h) {
            const now = new Date();
            let days = 3;
            
            if (delivery === '2d') days = 2;
            else if (delivery === '1d') days = 1;
            
            const deliveryDate = new Date(now);
            deliveryDate.setDate(now.getDate() + days);
            
            if (buffer24h) {
                deliveryDate.setDate(deliveryDate.getDate() + 1);
            }
            
            return deliveryDate.toLocaleDateString('de-DE', {
                weekday: 'short',
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        updatePackageInclusiveExtras(packageIndex) {
            // Clear current package-inclusive extras
            this.state.extras = this.state.extras.filter(extra => !extra.included_packages || !extra.included_packages.includes(packageIndex));
            
            // Auto-select extras that are included in this package
            $('.lm-extras-checkbox').each((index, checkbox) => {
                const $checkbox = $(checkbox);
                const extra = JSON.parse($checkbox.val());
                const includedPackages = extra.included_packages || [];
                
                if (includedPackages.includes(packageIndex)) {
                    $checkbox.prop('checked', true).addClass('package-inclusive');
                    if (!this.state.extras.find(e => e.label === extra.label)) {
                        this.state.extras.push(extra);
                    }
                } else {
                    $checkbox.removeClass('package-inclusive');
                }
            });
        }

        updateState() {
            // Calculate norm pages
            this.state.normPages = this.calculateNormPages(this.state.words);
            
            // Calculate totals
            const base = this.state.normPages * this.state.pricePerPage;
            // Only count extras that are not package-inclusive
            const extrasTotal = this.state.extras.reduce((sum, extra) => {
                const isPackageInclusive = extra.package_inclusive || false;
                const includedInSelectedPackage = extra.included_packages && extra.included_packages.includes(this.state.selectedPackageIndex);
                const shouldExclude = isPackageInclusive || includedInSelectedPackage;
                return sum + (shouldExclude ? 0 : extra.price);
            }, 0);
            const subtotal = base + extrasTotal;
            
            // Get delivery surcharge from settings (same as backend)
            let multiplier = 1.00;
            let surcharge = 0;
            
            // Parse delivery days from format like "2d", "1d", "3d"
            const deliveryDays = parseInt(this.state.delivery.replace('d', ''));
            
            // Apply surcharge based on delivery days (matching backend logic)
            if (deliveryDays === 2) {
                surcharge = 15; // 15% surcharge for 2-day delivery
                multiplier = 1.15;
            } else if (deliveryDays === 1) {
                surcharge = 50; // 50% surcharge for 1-day delivery
                multiplier = 1.50;
            } else {
                surcharge = 0; // No surcharge for 3-day delivery
                multiplier = 1.00;
            }
            
            let total = Math.round(subtotal * multiplier * 100) / 100;
            
            // Initialize totals object if it doesn't exist
            if (!this.state.totals) {
                this.state.totals = {};
            }
            
            // Store original total before voucher discount
            this.state.totals.originalTotal = total;
            
            
            // Apply voucher discount if applicable
            if (this.state.voucher.applied && this.state.voucher.discount > 0) {
                const discountAmount = total * (this.state.voucher.discount / 100);
                total = Math.round((total - discountAmount) * 100) / 100;
                this.state.totals.discountAmount = discountAmount;
                
            } else {
                this.state.totals.discountAmount = 0;
            }
            
            
            this.state.totals = {
                base: base,
                extras: extrasTotal,
                surcharge: surcharge,
                total: total,
                originalTotal: this.state.totals.originalTotal,
                discountAmount: this.state.totals.discountAmount
            };
            
            this.state.deliveryDate = this.calculateDeliveryDate(this.state.delivery, lmBookingAjax.delivery.buffer24h);
            
            this.updateSummary();
            this.updateHiddenFields();
        }

        updateSummary() {
            // Ensure totals exist before trying to display them
            if (!this.state.totals || typeof this.state.totals.total === 'undefined') {
                $('#lm-total-display').text('€0');
                return;
            }


            // Update total display - show both original and discounted prices if voucher is applied
            if (this.state.voucher.applied && this.state.voucher.discount > 0 && this.state.totals.originalTotal) {
                const originalPrice = this.formatCurrency(this.state.totals.originalTotal);
                const discountedPrice = this.formatCurrency(this.state.totals.total);
                const discountAmount = this.formatCurrency(this.state.totals.discountAmount || 0);
                
                
                $('#lm-total-display').html(`
                    <span class="lm-original-price">${originalPrice}€</span>
                    <span class="lm-discounted-price">${discountedPrice}€</span>
                    <span class="lm-discount-info">(-${discountAmount}€)</span>
                `);
            } else {
                $('#lm-total-display').text(`${this.formatCurrency(this.state.totals.total)}€`);
            }
            $('#lm-delivery-date-display').text(this.state.deliveryDate);
            
            // Update summary details
            $('#lm-summary-service').text(this.state.service || '-');
            $('#lm-summary-words').text(this.state.words || '-');
            
            // Update delivery summary
            let deliveryText = '-';
            if (this.state.delivery === '1d') deliveryText = '1 Tag';
            else if (this.state.delivery === '2d') deliveryText = '2 Tage';
            else if (this.state.delivery === '3d') deliveryText = '3 Tage';
            $('#lm-summary-delivery').text(deliveryText);
            
            // Update extras summary
            if (this.state.extras.length > 0) {
                const extrasList = this.state.extras.map(extra => `<li>${extra.label}</li>`).join('');
                $('#lm-summary-extras').html(`<ul>${extrasList}</ul>`);
            } else {
                $('#lm-summary-extras').text(lmBookingAjax.i18n.labels.noExtras);
            }
        }

        updateHiddenFields() {
            $('#lm-total').val(this.state.totals.total);
            $('#lm-norm-pages').val(this.state.normPages);
            $('#lm-delivery-date').val(this.state.deliveryDate);
            
            const breakdown = JSON.stringify({
                normPages: this.state.normPages,
                base: this.state.totals.base,
                extras: this.state.extras,
                extrasTotal: this.state.totals.extras,
                subtotal: this.state.totals.base + this.state.totals.extras,
                surcharge: this.state.totals.surcharge,
                total: this.state.totals.total,
                delivery: this.state.delivery,
                multiplier: this.state.delivery === '2d' ? 1.15 : (this.state.delivery === '1d' ? 1.50 : 1.00)
            });
            $('#lm-breakdown').val(breakdown);
        }

        updateStepper() {
            this.$stepperItems.removeClass('lm-stepper-active lm-stepper-completed');
            
            // Handle success step - mark all steps as completed
            if (this.currentStep === 'success') {
                this.$stepperItems.addClass('lm-stepper-completed');
                return;
            }
            
            // Handle regular steps - use more reliable selector
            this.$stepperItems.each((index, element) => {
                const $item = $(element);
                const stepNumber = index + 1;
                
                if (stepNumber < this.currentStep) {
                    $item.addClass('lm-stepper-completed');
                } else if (stepNumber === this.currentStep) {
                    $item.addClass('lm-stepper-active');
                }
            });
        }

        validateStep(step) {
            switch (step) {
                case 1:
                    if (!this.state.service) {
                        this.showError(lmBookingAjax.i18n.validation.required);
                        return false;
                    }
                    if (this.state.words < 250) {
                        this.showError('Mindestens 250 Wörter erforderlich.');
                        return false;
                    }
                    if (!this.state.fileUploaded) {
                        this.showError('Bitte laden Sie eine Datei hoch (DOC, DOCX, ODT oder RTF).', true);
                        return false;
                    }
                    return true;
                    
                case 2:
                    return true; // Extras are optional
                    
                case 3:
                    if (!this.state.customer.name.trim()) {
                        this.showError(lmBookingAjax.i18n.validation.required);
                        return false;
                    }
                    if (!this.state.customer.email.trim() || !this.isValidEmail(this.state.customer.email)) {
                        this.showError(lmBookingAjax.i18n.validation.invalidEmail);
                        return false;
                    }
                    if (!this.state.customer.country) {
                        this.showError(lmBookingAjax.i18n.validation.required);
                        return false;
                    }
                    if (!this.state.customer.program) {
                        this.showError(lmBookingAjax.i18n.validation.required);
                        return false;
                    }
                    if (!this.state.consent) {
                        this.showError(lmBookingAjax.i18n.validation.consentRequired);
                        return false;
                    }
                    return true;
                    
                default:
                    return true;
            }
        }

        isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        nextStep() {
            if (!this.validateStep(this.currentStep)) {
                return;
            }

            this.hideError();

            if (this.currentStep < 3) {
                this.currentStep++;
                
                // Skip step 2 (extras) if it doesn't exist (no extras configured)
                if (this.currentStep === 2 && $('#lm-step-2').length === 0) {
                    this.currentStep = 3;
                }
                
                this.showStep(this.currentStep);
                this.updateStepper();
                this.updateCTA();
            } else {
                this.submitForm();
            }
        }

        showStep(step) {
            this.$steps.hide();
            
            // Handle success step
            if (step === 'success') {
                $('#lm-step-success').show();
                return;
            }
            
            // Handle regular steps
            $(`#lm-step-${step}`).show();
            
            // Focus first input in the step
            const $firstInput = $(`#lm-step-${step} input, #lm-step-${step} select, #lm-step-${step} textarea`).first();
            if ($firstInput.length) {
                $firstInput.focus();
            }
        }

        updateCTA() {
            const ctaTexts = {
                1: lmBookingAjax.i18n.buttons.toExtras,
                2: lmBookingAjax.i18n.buttons.lastStep,
                3: lmBookingAjax.i18n.buttons.getOffer
            };
            
            this.$cta.text(ctaTexts[this.currentStep] || lmBookingAjax.i18n.buttons.next);
            
            // Show voucher and consent sections on step 3
            if (this.currentStep === 3) {
                this.$summaryVoucher.show();
                this.$summaryConsent.show();
            } else {
                this.$summaryVoucher.hide();
                this.$summaryConsent.hide();
            }
        }

        submitForm() {
            this.$cta.prop('disabled', true).text('Wird gesendet...');

            const formData = new FormData();
            formData.append('action', 'lm_booking_submit');
            formData.append('nonce', lmBookingAjax.nonce);
            formData.append('service', this.state.service);
            formData.append('words', this.state.words);
            formData.append('delivery', this.state.delivery);
            formData.append('extras', JSON.stringify(this.state.extras));
            formData.append('total', this.state.totals.total);
            formData.append('breakdown', $('#lm-breakdown').val());
            formData.append('norm_pages', this.state.normPages);
            formData.append('delivery_date', this.state.deliveryDate);
            formData.append('name', this.state.customer.name);
            formData.append('email', this.state.customer.email);
            formData.append('country', this.state.customer.country);
            formData.append('program', this.state.customer.program);
            formData.append('note', this.state.customer.note);
            formData.append('consent', this.state.consent ? '1' : '0');
            formData.append('voucher_code', this.state.voucher.code);
            formData.append('voucher_discount', this.state.voucher.discount);
            formData.append('original_total', this.state.totals.originalTotal || this.state.totals.total);

            // Add file if uploaded
            const fileInput = $('#lm-upload-input')[0];
            if (fileInput && fileInput.files[0]) {
                formData.append('document', fileInput.files[0]);
            }

            $.ajax({
                url: lmBookingAjax.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: (response) => {
                    if (response.success) {
                        this.showSuccess();
                    } else {
                        this.showError(response.data.message || 'Ein Fehler ist aufgetreten.');
                    }
                },
                error: (xhr, status, error) => {
                    this.showError('Netzwerkfehler. Bitte versuchen Sie es erneut.');
                },
                complete: () => {
                    this.$cta.prop('disabled', false);
                }
            });
        }

        showSuccess() {
            this.currentStep = 'success';
            this.showStep('success');
            this.updateStepper();
            
            // Disable voucher button and consent checkbox after successful booking
            $('#lm-apply-voucher').prop('disabled', true).prop('readonly', true);
            $('#lm-voucher-code').prop('disabled', true).prop('readonly', true);
            $('#lm-consent-checkbox').prop('disabled', true);
            
            this.$cta.text(lmBookingAjax.i18n.buttons.backToHome).off('click').on('click', () => {
                window.location.href = '/';
            });
        }

        showError(message, isFileUploadError = false) {
            if (isFileUploadError) {
                // For file upload errors, show below the CTA button
                let $fileError = $('#lm-file-upload-error');
                if ($fileError.length === 0) {
                    // Create error element
                    $fileError = $(`
                        <div id="lm-file-upload-error" class="lm-file-error" aria-live="assertive" style="display: none;">
                            <span class="lm-file-error-message"></span>
                        </div>
                    `);
                    this.$cta.after($fileError);
                }
                $fileError.find('.lm-file-error-message').text(message);
                $fileError.show();
                
                // File upload errors are persistent - no auto-hide
            } else {
                // For other errors, use the existing error element
                this.$errorMsg.text(message).show();
                this.$errorMsg.attr('aria-live', 'assertive');
                
                // Auto-hide error message after 3 seconds
                setTimeout(() => {
                    this.hideError();
                }, 3000);
            }
        }

        hideError() {
            this.$errorMsg.hide().text('');
        }

        hideFileUploadError() {
            $('#lm-file-upload-error').hide().text('');
        }



        handleFileUpload(file) {
            
            // File has already been validated in the change handler
            // This method is just for logging and any additional processing
        }

        resetUploadButton() {
            $('.lm-upload-btn').html(`
                <span class="lm-upload-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                    </svg>
                </span>
                Datei hochladen*
            `);
        }

        applyVoucher() {
            const voucherCode = $('#lm-voucher-code').val().trim().toUpperCase();
            const $message = $('#lm-voucher-message');
            const $btn = $('#lm-apply-voucher');

            if (!voucherCode) {
                this.showVoucherMessage('Bitte geben Sie einen Gutscheincode ein.', 'error');
                return;
            }

            $btn.prop('disabled', true).text('Wird geprüft...');

            // Call real voucher validation API
            $.ajax({
                url: lmBookingAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'lm_validate_voucher',
                    voucher_code: voucherCode,
                    customer_email: this.state.customer.email,
                    lm_voucher_nonce: lmBookingAjax.voucher_nonce
                },
                success: (response) => {
                    if (response.success) {
                        // Voucher is valid
                        this.state.voucher.code = response.data.voucher.code;
                        this.state.voucher.discount = response.data.voucher.discount;
                        this.state.voucher.applied = true;
                        
                        
                        this.showVoucherMessage(response.data.message, 'success');
                        $('#lm-voucher-code').prop('disabled', true).prop('readonly', true);
                        $btn.text('Angewendet').prop('disabled', true);
                        
                        this.updateState(); // Recalculate totals with discount
                    } else {
                        // Voucher is invalid
                        this.showVoucherMessage(response.data.message || 'Ungültiger oder abgelaufener Gutscheincode.', 'error');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Voucher validation error:', error);
                    this.showVoucherMessage('Ein Fehler ist aufgetreten. Bitte versuchen Sie es erneut.', 'error');
                },
                complete: () => {
                    $btn.prop('disabled', false).text('Anwenden');
                }
            });
        }

        showVoucherMessage(message, type) {
            const $message = $('#lm-voucher-message');
            $message.removeClass('success error').addClass(type).text(message).show();
            
            // Auto-hide success messages
            if (type === 'success') {
                setTimeout(() => {
                    $message.fadeOut();
                }, 3000);
            }
        }

        toggleInfoPopup($infoButton) {
            const description = $infoButton.data('description');
            if (!description) return;

            // Hide all other popups first
            this.hideAllInfoPopups();

            // Check if this popup is already showing
            const $existingPopup = $infoButton.find('.lm-info-popup');
            if ($existingPopup.length && $existingPopup.hasClass('show')) {
                $existingPopup.removeClass('show');
                return;
            }

            // Create or show popup
            let $popup = $existingPopup;
            if (!$popup.length) {
                $popup = $(`<div class="lm-info-popup">${description}</div>`);
                $infoButton.append($popup);
            }

            // Show popup with animation
            setTimeout(() => {
                $popup.addClass('show');
            }, 10);
        }

        hideAllInfoPopups() {
            $('.lm-info-popup').removeClass('show');
        }


    }

    // Initialize wizard
    new BookingWizard();
});

