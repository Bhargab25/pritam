<div>
    <x-mary-header title="Company Profile" subtitle="Manage your company information and settings" separator />

    {{-- Tabs Navigation --}}
    <div class="mb-6">
        <div class="bg-gray-100 rounded-xl p-1 inline-flex flex-wrap gap-1">
            <button wire:click="switchTab('basic')"
                class="px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200 
                {{ $activeTab === 'basic' ? 'bg-primary text-white shadow-lg' : 'text-gray-600 hover:text-gray-800 hover:bg-gray-200' }}">
                Basic Info
            </button>
            <button wire:click="switchTab('address')"
                class="px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200 
                {{ $activeTab === 'address' ? 'bg-primary text-white shadow-lg' : 'text-gray-600 hover:text-gray-800 hover:bg-gray-200' }}">
                Address
            </button>
            <button wire:click="switchTab('tax')"
                class="px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200 
                {{ $activeTab === 'tax' ? 'bg-primary text-white shadow-lg' : 'text-gray-600 hover:text-gray-800 hover:bg-gray-200' }}">
                Tax & Legal
            </button>
            {{-- <button wire:click="switchTab('banking')"
                class="px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200 
                {{ $activeTab === 'banking' ? 'bg-primary text-white shadow-lg' : 'text-gray-600 hover:text-gray-800 hover:bg-gray-200' }}">
                Banking
            </button> --}}
            <button wire:click="switchTab('details')"
                class="px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200 
                {{ $activeTab === 'details' ? 'bg-primary text-white shadow-lg' : 'text-gray-600 hover:text-gray-800 hover:bg-gray-200' }}">
                Details
            </button>
            <button wire:click="switchTab('social')"
                class="px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200 
                {{ $activeTab === 'social' ? 'bg-primary text-white shadow-lg' : 'text-gray-600 hover:text-gray-800 hover:bg-gray-200' }}">
                Social Media
            </button>
            <button wire:click="switchTab('branding')"
                class="px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200 
                {{ $activeTab === 'branding' ? 'bg-primary text-white shadow-lg' : 'text-gray-600 hover:text-gray-800 hover:bg-gray-200' }}">
                Branding
            </button>
            <button wire:click="switchTab('settings')"
                class="px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200 
                {{ $activeTab === 'settings' ? 'bg-primary text-white shadow-lg' : 'text-gray-600 hover:text-gray-800 hover:bg-gray-200' }}">
                Settings
            </button>
            <button wire:click="switchTab('banking_accounts')"
                class="px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200 
                {{ $activeTab === 'banking_accounts' ? 'bg-primary text-white shadow-lg' : 'text-gray-600 hover:text-gray-800 hover:bg-gray-200' }}">
                Bank Accounts
            </button>
        </div>
    </div>

    {{-- Tab Content --}}
    @if($activeTab === 'basic')
    <x-mary-card>
        <x-mary-header title="Basic Information" subtitle="Company name, contact information" />

        <div class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-mary-input label="Company Name *" wire:model="name"
                    placeholder="Your Company Name" />

                <x-mary-input label="Legal Name" wire:model="legalName"
                    placeholder="Full Legal Company Name" />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-mary-input label="Email Address" wire:model="email"
                    placeholder="company@example.com" type="email" />

                <x-mary-input label="Website" wire:model="website"
                    placeholder="https://www.yourcompany.com" />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-mary-input label="Phone Number" wire:model="phone"
                    placeholder="+91 22 1234 5678" />

                <x-mary-input label="Mobile Number" wire:model="mobile"
                    placeholder="+91 98765 43210" />
            </div>

            <div class="flex justify-end">
                <x-mary-button label="Save Basic Information" class="btn-primary"
                    spinner="saveBasicInfo" @click="$wire.saveBasicInfo()" />
            </div>
        </div>
    </x-mary-card>

    @elseif($activeTab === 'address')
    <x-mary-card>
        <x-mary-header title="Address Information" subtitle="Company address and location details" />

        <div class="space-y-6">
            <x-mary-textarea label="Address" wire:model="address"
                rows="3" placeholder="Street address, building name, floor..." />

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <x-mary-input label="City" wire:model="city"
                    placeholder="Mumbai" />

                <x-mary-input label="State" wire:model="state"
                    placeholder="Maharashtra" />

                <x-mary-input label="Postal Code" wire:model="postalCode"
                    placeholder="400001" />
            </div>

            <x-mary-select label="Country *" wire:model="country"
                :options="[
                        ['value' => 'India', 'label' => 'India'],
                        ['value' => 'United States', 'label' => 'United States'],
                        ['value' => 'United Kingdom', 'label' => 'United Kingdom'],
                        ['value' => 'Canada', 'label' => 'Canada'],
                        ['value' => 'Australia', 'label' => 'Australia']
                    ]"
                option-value="value" option-label="label" />

            <div class="flex justify-end">
                <x-mary-button label="Save Address Information" class="btn-primary"
                    spinner="saveAddressInfo" @click="$wire.saveAddressInfo()" />
            </div>
        </div>
    </x-mary-card>

    @elseif($activeTab === 'tax')
    <x-mary-card>
        <x-mary-header title="Tax & Legal Information" subtitle="Registration numbers and legal identifiers" />

        <div class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-mary-input label="PAN Number" wire:model="panNumber"
                    placeholder="ABCDE1234F" hint="10-character PAN number" />

                <x-mary-input label="GSTIN" wire:model="gstin"
                    placeholder="22AAAAA0000A1Z5" hint="15-character GST number" />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-mary-input label="CIN" wire:model="cin"
                    placeholder="U12345AB1234PLC123456" hint="Corporate Identification Number" />

                <x-mary-input label="TAN Number" wire:model="tanNumber"
                    placeholder="ABCD12345E" hint="Tax Deduction Account Number" />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-mary-input label="FSSAI Number" wire:model="fssaiNumber"
                    placeholder="12345678901234" hint="Food Safety License Number" />

                <x-mary-input label="MSME Number" wire:model="msmeNumber"
                    placeholder="UDYAM-XX-00-0000000" hint="MSME Registration Number" />
            </div>

            <div class="flex justify-end">
                <x-mary-button label="Save Tax Information" class="btn-primary"
                    spinner="saveTaxInfo" @click="$wire.saveTaxInfo()" />
            </div>
        </div>
    </x-mary-card>

    @elseif($activeTab === 'banking')
    <x-mary-card>
        <x-mary-header title="Banking Information" subtitle="Company bank account details" />

        <div class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-mary-input label="Bank Name" wire:model="bankName"
                    placeholder="State Bank of India" />

                <x-mary-input label="Branch" wire:model="bankBranch"
                    placeholder="Mumbai Main Branch" />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-mary-input label="Account Number" wire:model="bankAccountNumber"
                    placeholder="1234567890" />

                <x-mary-input label="IFSC Code" wire:model="bankIfscCode"
                    placeholder="SBIN0001234" hint="11-character IFSC code" />
            </div>

            <div class="flex justify-end">
                <x-mary-button label="Save Banking Information" class="btn-primary"
                    spinner="saveBankingInfo" @click="$wire.saveBankingInfo()" />
            </div>
        </div>
    </x-mary-card>

    @elseif($activeTab === 'details')
    <x-mary-card>
        <x-mary-header title="Company Details" subtitle="Additional company information" />

        <div class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-mary-input label="Established Date" wire:model="establishedDate"
                    type="date" />

                <x-mary-select label="Business Type" wire:model="businessType"
                    :options="[
                            ['value' => '', 'label' => 'Select Business Type'],
                            ['value' => 'proprietorship', 'label' => 'Proprietorship'],
                            ['value' => 'partnership', 'label' => 'Partnership'],
                            ['value' => 'llp', 'label' => 'Limited Liability Partnership (LLP)'],
                            ['value' => 'private_limited', 'label' => 'Private Limited Company'],
                            ['value' => 'public_limited', 'label' => 'Public Limited Company'],
                            ['value' => 'other', 'label' => 'Other']
                        ]"
                    option-value="value" option-label="label" />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-mary-input label="Industry" wire:model="industry"
                    placeholder="Technology, Manufacturing, Retail, etc." />

                <x-mary-input label="Employee Count" wire:model="employeeCount"
                    type="number" min="1" placeholder="50" />
            </div>

            <x-mary-textarea label="Business Description" wire:model="businessDescription"
                rows="4" placeholder="Describe your business activities..." />

            <div class="flex justify-end">
                <x-mary-button label="Save Company Details" class="btn-primary"
                    spinner="saveCompanyDetails" @click="$wire.saveCompanyDetails()" />
            </div>
        </div>
    </x-mary-card>

    @elseif($activeTab === 'social')
    <x-mary-card>
        <x-mary-header title="Social Media" subtitle="Social media profiles and links" />

        <div class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-mary-input label="Facebook URL" wire:model="facebookUrl"
                    placeholder="https://www.facebook.com/yourcompany"
                    prefix="facebook.com/" />

                <x-mary-input label="Twitter URL" wire:model="twitterUrl"
                    placeholder="https://www.twitter.com/yourcompany"
                    prefix="twitter.com/" />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-mary-input label="LinkedIn URL" wire:model="linkedinUrl"
                    placeholder="https://www.linkedin.com/company/yourcompany"
                    prefix="linkedin.com/" />

                <x-mary-input label="Instagram URL" wire:model="instagramUrl"
                    placeholder="https://www.instagram.com/yourcompany"
                    prefix="instagram.com/" />
            </div>

            <div class="flex justify-end">
                <x-mary-button label="Save Social Media Links" class="btn-primary"
                    spinner="saveSocialMedia" @click="$wire.saveSocialMedia()" />
            </div>
        </div>
    </x-mary-card>

    @elseif($activeTab === 'branding')
    <x-mary-card>
        <x-mary-header title="Branding Assets" subtitle="Upload company logos and branding materials" />

        <div class="space-y-8">
            {{-- Logo Upload --}}
            <div class="border border-gray-200 rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Company Logo</h3>

                @if($currentLogoPath)
                <div class="mb-4 flex items-center gap-4">
                    <img src="{{ Storage::url($currentLogoPath) }}" alt="Company Logo"
                        class="w-24 h-24 object-contain border rounded">
                    <div>
                        <p class="text-sm text-gray-600 mb-2">Current logo</p>
                        <x-mary-button label="Remove" class="btn-error btn-sm"
                            @click="$wire.removeFile('logo')" />
                    </div>
                </div>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
                    <x-mary-file label="Upload New Logo" wire:model="logo"
                        accept="image/*" hint="Max 2MB (JPEG, PNG, SVG)" />

                    <x-mary-button label="Upload Logo" class="btn-primary"
                        :disabled="!$logo" spinner="uploadLogo" @click="$wire.uploadLogo()" />
                </div>
            </div>

            {{-- Favicon Upload --}}
            <div class="border border-gray-200 rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Favicon</h3>

                @if($currentFaviconPath)
                <div class="mb-4 flex items-center gap-4">
                    <img src="{{ Storage::url($currentFaviconPath) }}" alt="Favicon"
                        class="w-8 h-8 object-contain border rounded">
                    <div>
                        <p class="text-sm text-gray-600 mb-2">Current favicon</p>
                        <x-mary-button label="Remove" class="btn-error btn-sm"
                            @click="$wire.removeFile('favicon')" />
                    </div>
                </div>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
                    <x-mary-file label="Upload Favicon" wire:model="favicon"
                        accept="image/*,.ico" hint="Max 1MB (ICO, PNG recommended)" />

                    <x-mary-button label="Upload Favicon" class="btn-primary"
                        :disabled="!$favicon" spinner="uploadFavicon" @click="$wire.uploadFavicon()" />
                </div>
            </div>

            {{-- Letterhead Upload --}}
            <div class="border border-gray-200 rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Letterhead</h3>

                @if($currentLetterheadPath)
                <div class="mb-4 flex items-center gap-4">
                    @if(str_ends_with($currentLetterheadPath, '.pdf'))
                    <div class="w-24 h-24 bg-red-100 border rounded flex items-center justify-center">
                        <span class="text-xs text-red-600">PDF</span>
                    </div>
                    @else
                    <img src="{{ Storage::url($currentLetterheadPath) }}" alt="Letterhead"
                        class="w-24 h-32 object-contain border rounded">
                    @endif
                    <div>
                        <p class="text-sm text-gray-600 mb-2">Current letterhead</p>
                        <x-mary-button label="Remove" class="btn-error btn-sm"
                            @click="$wire.removeFile('letterhead')" />
                    </div>
                </div>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
                    <x-mary-file label="Upload Letterhead" wire:model="letterhead"
                        accept="image/*,.pdf" hint="Max 5MB (PDF, JPEG, PNG)" />

                    <x-mary-button label="Upload Letterhead" class="btn-primary"
                        :disabled="!$letterhead" spinner="uploadLetterhead" @click="$wire.uploadLetterhead()" />
                </div>
            </div>

            {{-- Signature Upload --}}
            <div class="border border-gray-200 rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Digital Signature</h3>

                @if($currentSignaturePath)
                <div class="mb-4 flex items-center gap-4">
                    <img src="{{ Storage::url($currentSignaturePath) }}" alt="Signature"
                        class="w-32 h-16 object-contain border rounded bg-white">
                    <div>
                        <p class="text-sm text-gray-600 mb-2">Current signature</p>
                        <x-mary-button label="Remove" class="btn-error btn-sm"
                            @click="$wire.removeFile('signature')" />
                    </div>
                </div>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
                    <x-mary-file label="Upload Signature" wire:model="signature"
                        accept="image/*" hint="Max 1MB (PNG with transparent background recommended)" />

                    <x-mary-button label="Upload Signature" class="btn-primary"
                        :disabled="!$signature" spinner="uploadSignature" @click="$wire.uploadSignature()" />
                </div>
            </div>
        </div>
    </x-mary-card>

    @elseif($activeTab === 'banking_accounts')
    <x-mary-card>
        <div class="flex justify-between items-center mb-6">
            <x-mary-header title="Bank Accounts" subtitle="Manage multiple bank accounts" />
            <x-mary-button label="Add Bank Account" icon="o-plus" class="btn-primary"
                @click="$wire.openBankAccountModal()" />
        </div>

        {{-- Bank Accounts List --}}
        <div class="space-y-4">
            @forelse($bankAccounts as $account)
            <div class="border rounded-lg p-4 {{ $account->is_default ? 'border-primary bg-primary/5' : '' }}">
                <div class="flex justify-between items-start">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-2">
                            <h3 class="text-lg font-semibold">{{ $account->account_name }}</h3>
                            @if($account->is_default)
                            <span class="badge badge-primary badge-sm">Default</span>
                            @endif
                            @if(!$account->is_active)
                            <span class="badge badge-error badge-sm">Inactive</span>
                            @endif
                        </div>

                        <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                            <div>
                                <span class="text-gray-600">Bank:</span>
                                <span class="font-medium">{{ $account->bank_name }}</span>
                            </div>
                            <div>
                                <span class="text-gray-600">Account:</span>
                                <span class="font-medium">{{ $account->masked_account_number }}</span>
                            </div>
                            <div>
                                <span class="text-gray-600">IFSC:</span>
                                <span class="font-medium">{{ $account->ifsc_code }}</span>
                            </div>
                            <div>
                                <span class="text-gray-600">Type:</span>
                                <span class="font-medium">{{ ucfirst($account->account_type) }}</span>
                            </div>
                            <div class="col-span-2">
                                <span class="text-gray-600">Current Balance:</span>
                                <span class="font-bold text-lg {{ $account->current_balance >= 0 ? 'text-success' : 'text-error' }}">
                                    ₹{{ number_format($account->current_balance, 2) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <x-mary-button icon="o-pencil" class="btn-sm btn-ghost"
                            @click="$wire.editBankAccount({{ $account->id }})" />
                        <x-mary-button icon="o-eye" class="btn-sm btn-ghost"
                            @click="$wire.viewBankTransactions({{ $account->id }})" />
                        @if(!$account->is_default)
                        {{-- <x-mary-button icon="o-trash" class="btn-sm btn-ghost btn-error"
                            @click="$wire.deleteBankAccount({{ $account->id }})" /> --}}
                        @endif
                    </div>
                </div>
            </div>
            @empty
            <div class="text-center py-8 text-gray-500">
                No bank accounts added yet. Click "Add Bank Account" to get started.
            </div>
            @endforelse
        </div>
    </x-mary-card>

    {{-- Bank Account Modal --}}
    <x-mary-modal wire:model="showBankAccountModal" title="{{ $editingBankAccount ? 'Edit' : 'Add' }} Bank Account">
        <div class="space-y-4">
            <x-mary-input label="Account Name *" wire:model="bankAccountForm.account_name"
                placeholder="e.g., Main Operating Account" />

            <div class="grid grid-cols-2 gap-4">
                <x-mary-input label="Bank Name *" wire:model="bankAccountForm.bank_name"
                    placeholder="State Bank of India" />

                <x-mary-input label="Branch Name" wire:model="bankAccountForm.branch_name"
                    placeholder="Mumbai Main Branch" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <x-mary-input label="Account Number *" wire:model="bankAccountForm.account_number"
                    placeholder="1234567890" />

                <x-mary-input label="IFSC Code *" wire:model="bankAccountForm.ifsc_code"
                    placeholder="SBIN0001234" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <x-mary-select label="Account Type *" wire:model="bankAccountForm.account_type"
                    :options="[
                        ['value' => 'savings', 'label' => 'Savings'],
                        ['value' => 'current', 'label' => 'Current'],
                        ['value' => 'overdraft', 'label' => 'Overdraft'],
                        ['value' => 'cash_credit', 'label' => 'Cash Credit']
                    ]"
                    option-value="value" option-label="label" />

                <x-mary-input label="Opening Balance" wire:model.number="bankAccountForm.opening_balance"
                    type="number" step="0.01" prefix="₹" />
            </div>

            <x-mary-textarea label="Notes" wire:model="bankAccountForm.notes"
                rows="2" placeholder="Any additional notes..." />

            <div class="flex gap-4">
                <x-mary-checkbox label="Set as Default Account" wire:model="bankAccountForm.is_default" />
                <x-mary-checkbox label="Active" wire:model="bankAccountForm.is_active" />
            </div>
        </div>

        <x-slot:actions>
            <x-mary-button label="Cancel" @click="$wire.closeBankAccountModal()" />
            <x-mary-button label="Save" class="btn-primary"
                spinner="saveBankAccount" @click="$wire.saveBankAccount()" />
        </x-slot:actions>
    </x-mary-modal>

    @else
    <x-mary-card>
        <x-mary-header title="Company Settings" subtitle="Financial and operational settings" />

        <div class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <x-mary-select label="Financial Year Start *" wire:model="financialYearStart"
                    :options="[
                            ['value' => '04-01', 'label' => 'April 1st (India)'],
                            ['value' => '01-01', 'label' => 'January 1st'],
                            ['value' => '07-01', 'label' => 'July 1st']
                        ]"
                    option-value="value" option-label="label" />

                <x-mary-select label="Currency *" wire:model="currency"
                    :options="[
                            ['value' => 'INR', 'label' => 'Indian Rupee (₹)'],
                            ['value' => 'USD', 'label' => 'US Dollar ($)'],
                            ['value' => 'EUR', 'label' => 'Euro (€)'],
                            ['value' => 'GBP', 'label' => 'British Pound (£)']
                        ]"
                    option-value="value" option-label="label" />

                <x-mary-select label="Timezone *" wire:model="timezone"
                    :options="[
                            ['value' => 'Asia/Kolkata', 'label' => 'Asia/Kolkata'],
                            ['value' => 'UTC', 'label' => 'UTC'],
                            ['value' => 'America/New_York', 'label' => 'America/New_York'],
                            ['value' => 'Europe/London', 'label' => 'Europe/London']
                        ]"
                    option-value="value" option-label="label" />
            </div>

            <div class="flex justify-end">
                <x-mary-button label="Save Settings" class="btn-primary"
                    spinner="saveSettings" @click="$wire.saveSettings()" />
            </div>
        </div>
    </x-mary-card>
    @endif
</div>