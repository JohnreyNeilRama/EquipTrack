{{-- "I agree to the Privacy Notice" checkbox (Data Privacy Act of 2012),
     required on Register and Set Your Password. Same look as "Remember me";
     the link opens the notice in a new tab so the form isn't lost. --}}
<label class="remember-me privacy-consent" for="privacy">
    <input type="checkbox" id="privacy" name="privacy" value="1" required @checked(old('privacy'))
           data-required-message="Please agree to the Privacy Notice to continue.">
    <span class="checkmark"></span>
    <span>I have read the EquipTrack <a href="{{ route('privacy') }}" target="_blank" rel="noopener">Privacy Notice</a> and agree to the collection and use of my personal information as described.</span>
</label>
