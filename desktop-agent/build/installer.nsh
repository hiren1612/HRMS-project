; Shown during installation so nobody discovers afterwards what this does.
;
; The brief requires transparent monitoring. A silent install that says
; nothing is the opposite of that, so the notice is part of the installer
; rather than something left to a policy document nobody opens.

!macro customWelcomePage
!macroend

!macro customInstall
  DetailPrint "HRMS Tracking Agent records active and idle time during working hours."
  DetailPrint "It may take periodic screenshots. A visible marker is shown whenever it does."
  DetailPrint "It does not record keystrokes or the content of what you type."
!macroend

!macro customUnInstall
  ; Remove the Run entry so a removed agent cannot come back at next logon.
  ;
  ; The value name matters and is easy to get wrong. Electron's
  ; app.setLoginItemSettings() registers under "electron.app." + the product
  ; name, not the product name alone. Deleting only the bare name leaves the
  ; real entry behind, and Windows then tries to launch a deleted executable
  ; at every logon for the life of the account. Found by uninstalling and
  ; looking, which is the only way this shows up.
  ;
  ; Both names are removed: the one Electron actually writes, and the bare
  ; one in case the agent is ever changed to register it itself.
  DeleteRegValue HKCU "Software\Microsoft\Windows\CurrentVersion\Run" "electron.app.HRMS Tracking Agent"
  DeleteRegValue HKCU "Software\Microsoft\Windows\CurrentVersion\Run" "HRMS Tracking Agent"
!macroend
